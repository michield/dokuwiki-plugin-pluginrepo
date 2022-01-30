<?php
/**
 * DokuWiki plugin/template/popularity data repository API
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 * @author     Hakan Sandell <hakan.sandell@home.se>
 */

class helper_plugin_pluginrepo_repository extends DokuWiki_Plugin {

    public $dokuReleases; // array of DokuWiki releases (name & date)

    public $types = array(
        1   => 'Syntax',
        2   => 'Admin',
        4   => 'Action',
        8   => 'Render',
        16  => 'Helper',
        32  => 'Template',
        64  => 'Remote',
        128 => 'Auth',
        256 => 'CLI',
        512 => 'CSS/JS-only'
    );

    public $obsoleteTag = '!obsolete';
    public $bundled;
    public $securitywarning = array('informationleak', 'allowsscript', 'requirespatch', 'partlyhidden');

    /**
     * helper_plugin_pluginrepo_repository constructor.
     */
    public function __construct() {
        $this->bundled = explode(',', $this->getConf('bundled'));
        $this->bundled = array_map('trim', $this->bundled);
        $this->bundled = array_filter($this->bundled);
        $this->bundled = array_unique($this->bundled);
    }

    /**
     * Parse syntax data block, return keyed array of values
     *
     *  You may use the # character to add comments to the block.
     *  Those will be ignored and will neither be displayed nor saved.
     *  If you need to enter # as data, escape it with a backslash (\#).
     *  If you need a backslash, escape it as well (\\)
     *
     * @param string $match data block
     * @return array
     */
    public function parseData($match) {
        // get lines
        $lines = explode("\n", $match);
        array_pop($lines);
        array_shift($lines);

        // parse info
        $data = array();
        foreach($lines as $line) {
            // ignore comments and bullet syntax
            $line = preg_replace('/(?<![&\\\\])#.*$/', '', $line);
            $line = preg_replace('/^  \* /', '', $line);
            $line = str_replace('\\#', '#', $line);
            $line = trim($line);
            if(empty($line)) continue;
            list($key, $value) = preg_split('/\s*:\s*/', $line, 2);
            $key = strtolower($key);
            if($data[$key]) {
                $data[$key] .= ' '.trim($value);
            } else {
                $data[$key] = trim($value);
            }
        }
        // sqlite plugin compability (formerly used for templates)
        if($data['lastupdate_dt']) $data['lastupdate'] = $data['lastupdate_dt'];
        if($data['template_tags']) $data['tags'] = $data['template_tags'];
        if($data['author_mail']) {
            list($mail, $name) = preg_split('/\s+/', $data['author_mail'], 2);
            $data['author'] = $name;
            $data['email']  = $mail;
        }
        return $data;
    }

    /**
     * Rewrite plugin
     *
     * @param array $data (reference) data from entry::handle
     */
    public function harmonizeExtensionIDs(&$data) {
        foreach(array('similar', 'conflicts', 'depends') as $key) {
            $refs = explode(',', $data[$key]);
            $refs = array_map('trim', $refs);
            $refs = array_filter($refs);

            $updatedrefs = array();
            foreach($refs as $ref) {
                $ns = curNS($ref);
                if($ns === false) {
                    $ns = '';
                }
                $id = noNS($ref);
                if($ns == 'template' OR ($data['type'] == 'template' AND $ns === '')) {
                    $ns = 'template:';
                } elseif($ns == 'plugin' OR $ns === '') {
                    $ns = '';
                } else {
                    $ns = $ns . ':';
                }
                $updatedrefs[] = $ns . $id;
            }
            $data[$key] = implode(',', $updatedrefs);
        }
    }

    /**
     * Create database connection and return PDO object
     * the config option 'db_name' must contain the
     * DataSourceName, which consists of the PDO driver name,
     * followed by a colon, followed by the PDO driver-specific connection syntax
     * see http://se2.php.net/manual/en/pdo.construct.php
     *
     * Example: 'mysql:dbname=testdb;host=127.0.0.1'
     *      or  'sqlite2:C:\DokuWikiStickNew\dokuwiki\repo.sqlite'
     */
    public function _getPluginsDB() {
        global $conf;
        /** @var $db PDO */
        $db = null;
        try {
            $db = new PDO($this->getConf('db_name'), $this->getConf('db_user'), $this->getConf('db_pass'));
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            if($db->getAttribute(PDO::ATTR_DRIVER_NAME) == 'mysql') {
                // Running on mysql; do something mysql specific here
            }

        } catch(PDOException $e) {
            msg("Repository plugin: failed to connect to database (".$e->getMessage().")", -1);
            return null;
        }

        // trigger creation of tables if db empty
        try {
            $stmt = $db->prepare('SELECT 1 FROM plugin_depends LIMIT 1');
            $stmt->execute();
        } catch(PDOException $e) {
            $this->_initPluginDB($db);
        }

        if($conf['allowdebug']) {
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
        } else {
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
        }
        return $db;
    }

    /**
     * Return array of plugins with some metadata
     * Note: used by repository.php (e.g. for translation tool) and repo table
     *
     * @param array $filter with entries used
     *   'plugins'    (array) returns only data of named plugins
     *   'plugintype' (integer) filter by type, binary-code decimal so you can combine types
     *   'plugintag'  (string) filter by one tag
     *   'pluginsort' (string) sort by some specific columns (also shortcuts available)
     *   'showall'    (yes/no) default/unset is 'no' and obsolete plugins and security issues are not returned
     *   'includetemplates' (yes/no) default/unset is 'no' and template data will not be returned
     * @return array data per plugin
     */
    public function getPlugins($filter = null) {
        $db = $this->_getPluginsDB();
        if(!$db) return array();

        // return named plugins OR with certain tag/type
        $requestedplugins = $filter['plugins'];
        $type    = 0;
        $tag     = '';
        $where_requested = '';
        $requestedINvalues = array();
        if($requestedplugins) {
            if(!is_array($requestedplugins)) {
                $requestedplugins = array($requestedplugins);
            }
            list($requestedINsql, $requestedINvalues) = $this->prepareINstmt('requested', $requestedplugins);

            $where_requested = " AND A.plugin " . $requestedINsql;
        } else {
            $type = (int) $filter['plugintype'];
            $tag  = strtolower(trim($filter['plugintag']));
        }

        if($filter['showall'] == 'yes') {
            $where_filtered = "1";
            $values = array();
        } else {
            list($bundledINsql, $bundledINvalues) = $this->prepareINstmt('bundled', $this->bundled);

            $where_filtered = "'" . $this->obsoleteTag . "' NOT IN(SELECT tag FROM plugin_tags WHERE plugin_tags.plugin = A.plugin)"
                . " AND A.securityissue = ''"
                . " AND (A.downloadurl <> '' OR A.plugin " . $bundledINsql . ")";
            $values = $bundledINvalues;
        }
        if($filter['includetemplates'] != 'yes') {
            $where_filtered .= " AND A.type <> 32"; // templates are only type=32, has no other type.
        }

        $sort    = strtolower(trim($filter['pluginsort']));
        $sortsql = $this->_getPluginsSortSql($sort);

        if($tag) {
            if($type < 1 or $type > 1023) {
                $type = 1023;
            }
            $sql = "      SELECT A.*, SUBSTR(A.plugin,10) as simplename
                                          FROM plugins A
                                         WHERE A.type = 32 AND $where_filtered
                                           AND (A.type & :plugin_type)
                                           AND :plugin_tag IN(SELECT tag FROM plugin_tags WHERE plugin_tags.plugin = A.plugin)
                                 UNION
                                        SELECT A.*, A.plugin as simplename
                                          FROM plugins A
                                         WHERE A.type <> 32 AND $where_filtered
                                           AND (A.type & :plugin_type)
                                           AND :plugin_tag IN(SELECT tag FROM plugin_tags WHERE plugin_tags.plugin = A.plugin)
                                $sortsql";
            $values = array_merge(
                array(':plugin_tag' => $tag,
                      ':plugin_type' => $type),
                $values
            );

        } elseif($type > 0 and $type <= 1023) {
            $sql = "      SELECT A.*, SUBSTR(A.plugin,10) as simplename
                                          FROM plugins A
                                         WHERE A.type = 32 AND $where_filtered
                                           AND (A.type & :plugin_type)
                                 UNION
                                        SELECT A.*, A.plugin as simplename
                                          FROM plugins A
                                         WHERE A.type <> 32 AND $where_filtered
                                           AND (A.type & :plugin_type)
                                 $sortsql";
            $values = array_merge(
                array(':plugin_type' => $type),
                $values
            );

        } else {
            $sql = "      SELECT A.*, SUBSTR(A.plugin,10) as simplename
                                          FROM plugins A
                                         WHERE A.type = 32 AND $where_filtered
                                    $where_requested
                                 UNION
                                        SELECT A.*, A.plugin as simplename
                                          FROM plugins A
                                         WHERE A.type <> 32 AND $where_filtered
                                    $where_requested
                                 $sortsql";

            $values = array_merge(
                $requestedINvalues,
                $values
            );
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($values);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Prepares IN statement with placeholders and array with the placeholder values
     *
     * @param string $paramlabel
     * @param array $values
     * @return array with
     *      sql as string
     *      params as associated array
     */
    protected function prepareINstmt($paramlabel, $values) {
        $count = 0;
        $params  = array();
        foreach($values as $value) {
            $params[':' . $paramlabel . $count++] = $value;
        }

        $sql = 'IN ('.join(',', array_keys($params)).')';
        return array(
            $sql,
            $params
        );
    }

    /**
     * Returns all plugins and templates from the database
     *
     * @return array extensions same as above, but without 'simplename' column
     */
    public function getAllExtensions() {
        $db = $this->_getPluginsDB();
        if(!$db) return array();

        $sql = "SELECT A.*
                  FROM plugins A
                  ORDER BY A.plugin";

        $stmt = $db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Gives all available info on plugins. Used for API
     *
     * tags, similar, depends, conflicts have newline separated lists
     *
     * @param array  $names    names of wanted extensions (use template: prefix)
     * @param array  $emailids md5s of emails of wanted extension authors
     * @param int    $type     ANDed types you want, 0 for all
     * @param array  $tags     show only extensions with these tags
     * @param string $order    order by this column
     * @param int    $limit    number of items
     * @param string $fulltext search term for full text search
     *
     * @return array
     * @throws Exception
     */
    public function getFilteredPlugins(
        $names = array(),
        $emailids = array(),
        $type = 0,
        $tags = array(),
        $order = '',
        $limit = 0,
        $fulltext = ''
    ) {
        $fulltext = trim($fulltext);

        $maxpop = $this->getMaxPopularity();

        // default to all extensions
        if($type == 0) {
            foreach(array_keys($this->types) as $t) $type += $t;
        }

        // cleanup $order
        $order = preg_replace('/[^a-z]+/', '', $order);
        if($order == 'popularity') $order .= ' DESC';
        if($order == 'lastupdate') $order .= ' DESC';
        if($order == '') {
            if($fulltext) {
                $order = 'score DESC';
            } else {
                $order = 'plugin';
            }
        }

        // limit
        $limit = (int) $limit;
        if($limit) {
            $limit = "LIMIT $limit";
        } else {
            if($fulltext) {
                $limit = 'LIMIT 50';
            } else {
                $limit = '';
            }
        }

        // name filter
        $namefilter = '';
        $nameparams = array();
        if($names) {
            $count = 0;
            foreach($names as $name) {
                $nameparams[':name'.$count++] = $name;
            }

            $namefilter = 'AND A.plugin IN ('.join(',', array_keys($nameparams)).')';
        }

        // email filter
        $emailfilter = '';
        $emailparams = array();
        if($emailids) {
            $count = 0;
            foreach($emailids as $email) {
                $emailparams[':email'.$count++] = $email;
            }

            $emailfilter = 'AND MD5(LOWER(A.email)) IN ('.join(',', array_keys($emailparams)).')';
        }

        // tag filter
        $tagfilter = '';
        $tagparams = array();
        if($tags) {
            $count = 0;
            foreach($tags as $tag) {
                $tagparams[':tag'.$count++] = $tag;
            }

            $tagfilter = 'AND B.tag IN ('.join(',', array_keys($tagparams)).')';
        }

        // fulltext search
        $fulltextwhere  = '';
        $fulltextfilter = '';
        $fulltextparams = array();
        if($fulltext) {
            $fulltextwhere  = 'MATCH (A.plugin, A.name, A.description, A.author, A.tags)
                                 AGAINST (:fulltext WITH QUERY EXPANSION) AS score,';
            $fulltextfilter = 'AND MATCH (A.plugin, A.name, A.description, A.author, A.tags)
                                 AGAINST (:fulltext WITH QUERY EXPANSION)';
            $fulltextparams = array(':fulltext' => $fulltext);
        }

        $obsoletefilter = "AND '" . $this->obsoleteTag . "' NOT IN(SELECT tag FROM plugin_tags WHERE plugin_tags.plugin = A.plugin)";

        $sql = "SELECT A.*,
                       A.popularity/:maxpop as popularity,
                       MD5(LOWER(A.email)) as emailid,
                       $fulltextwhere
                       GROUP_CONCAT(DISTINCT B.tag ORDER BY B.tag SEPARATOR '\n') as tags,
                       GROUP_CONCAT(DISTINCT C.other ORDER BY C.other SEPARATOR '\n') as similar,
                       GROUP_CONCAT(DISTINCT D.other ORDER BY D.other SEPARATOR '\n') as depends,
                       GROUP_CONCAT(DISTINCT E.other ORDER BY E.other SEPARATOR '\n') as conflicts
                  FROM plugins A
             LEFT JOIN plugin_tags B
                    ON A.plugin = B.plugin
             LEFT JOIN plugin_similar C
                    ON A.plugin = C.plugin
             LEFT JOIN plugin_depends D
                    ON A.plugin = D.plugin
             LEFT JOIN plugin_conflicts E
                    ON A.plugin = E.plugin

                 WHERE (A.type & :type)
                       $namefilter
                       $tagfilter
                       $emailfilter
                       $fulltextfilter
                       $obsoletefilter
              GROUP BY A.plugin
              ORDER BY $order
                       $limit";

        $db = $this->_getPluginsDB();
        if(!$db) throw new Exception('Cannot connect to database');

        $parameters = array_merge(
            array(':type' => $type, ':maxpop' => $maxpop),
            $nameparams,
            $tagparams,
            $emailparams,
            $fulltextparams
        );

        $stmt = $db->prepare($sql);
        $stmt->execute($parameters);
        $plugins = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // add and cleanup few more fields
        $cnt = count($plugins);
        for($i = 0; $i < $cnt; $i++) {
            if(in_array($plugins[$i]['plugin'], $this->bundled)) {
                $plugins[$i]['bundled'] = true;
            } else {
                $plugins[$i]['bundled'] = false;
            }
            if(!$plugins[$i]['screenshot']) {
                $plugins[$i]['screenshoturl'] = null;
                $plugins[$i]['thumbnailurl']  = null;
            } else {
                $plugins[$i]['screenshoturl'] = ml($plugins[$i]['screenshot'], '', true, '&', true);
                $plugins[$i]['thumbnailurl']  = ml($plugins[$i]['screenshot'], array('w' => 120, 'h' => 70), true, '&', true);
            }
            unset($plugins[$i]['screenshot']);
            unset($plugins[$i]['email']); // no spam

            $plugins[$i]['depends']   = array_filter(explode("\n", $plugins[$i]['depends']));
            $plugins[$i]['similar']   = array_filter(explode("\n", $plugins[$i]['similar']));
            $plugins[$i]['conflicts'] = array_filter(explode("\n", $plugins[$i]['conflicts']));
            $plugins[$i]['tags']      = array_filter(explode("\n", $plugins[$i]['tags']));

            $plugins[$i]['compatible'] = $this->cleanCompat($plugins[$i]['compatible']);
            $plugins[$i]['types']      = $this->listtypes($plugins[$i]['type']);

            $plugins[$i]['securitywarning'] = $this->replaceSecurityWarningShortcut ($plugins[$i]['securitywarning']);

            ksort($plugins[$i]);
        }

        return $plugins;
    }

    /**
     * Translate sort keyword to sql clause
     * @param string $sort keyword in format [^]<columnnames|shortcut columnname>
     * @return string
     */
    private function _getPluginsSortSql($sort) {
        $sortsql = '';
        if($sort{0} == '^') {
            $sortsql = ' DESC';
            $sort    = substr($sort, 1);
        }
        if($sort == 'a' || $sort == 'author') {
            $sortsql = 'ORDER BY author'.$sortsql;
        } elseif($sort == 'd' || $sort == 'lastupdate') {
            $sortsql = 'ORDER BY lastupdate'.$sortsql;
        } elseif($sort == 't' || $sort == 'type') {
            $sortsql = 'ORDER BY type'.$sortsql.', simplename';
        } elseif($sort == 'v' || $sort == 'compatibility') {
            $sortsql = 'ORDER BY bestcompatible'.$sortsql.', simplename';
        } elseif($sort == 'c' || $sort == 'popularity') {
            $sortsql = 'ORDER BY popularity'.$sortsql;
        } elseif($sort == 'p' || $sort == 'plugin') {
            $sortsql = 'ORDER BY simplename'.$sortsql;
        } else {
            $sortsql = 'ORDER BY bestcompatible DESC, simplename'.$sortsql;
        }
        return $sortsql;
    }

    /**
     * @param string $id of plugin
     * @return array of metadata about plugin:
     *   'conflicts'  array of plugin names
     *   'similar'    array of plugin names
     *   'depends'    array of plugin names
     *   'needed'     array of plugin names
     *   'sameauthor' array of plugin names
     */
    public function getPluginRelations($id) {
        $db = $this->_getPluginsDB();
        if(!$db) return array();

        $id   = strtolower($id);
        $meta = array();

        /** @var $stmt PDOStatement */
        $stmt = $db->prepare('SELECT plugin,other FROM plugin_conflicts WHERE plugin = ? OR other = ?');
        $stmt->execute(array($id, $id));
        foreach($stmt as $row) {
            if($row['plugin'] == $id) $meta['conflicts'][] = $row['other'];
            elseif($row['other'] == $id) $meta['conflicts'][] = $row['plugin'];
        }

        $stmt = $db->prepare('SELECT plugin,other FROM plugin_similar WHERE plugin = ? OR other = ?');
        $stmt->execute(array($id, $id));
        foreach($stmt as $row) {
            if($row['plugin'] == $id) $meta['similar'][] = $row['other'];
            elseif($row['other'] == $id) $meta['similar'][] = $row['plugin'];
        }

        $stmt = $db->prepare('SELECT other FROM plugin_depends WHERE plugin = ? ');
        $stmt->execute(array($id));
        foreach($stmt as $row) {
            $meta['depends'][] = $row['other'];
        }

        $stmt = $db->prepare('SELECT plugin FROM plugin_depends WHERE other = ? ');
        $stmt->execute(array($id));
        foreach($stmt as $row) {
            $meta['needed'][] = $row['plugin'];
        }

        $stmt = $db->prepare('SELECT plugin FROM plugins WHERE plugin <> ? AND email <> "" AND email=(SELECT email FROM plugins WHERE plugin = ?)');
        $stmt->execute(array($id, $id));
        foreach($stmt as $row) {
            $meta['sameauthor'][] = $row['plugin'];
        }
        if(!empty($meta['conflicts'])) $meta['conflicts'] = array_unique($meta['conflicts']);
        if(!empty($meta['similar'])) $meta['similar'] = array_unique($meta['similar']);
        return $meta;
    }

    /**
     * Return array of tags and their frequency in the repository
     *
     * @param int $minlimit
     * @param array $filter with entries:
     *                  'showall' => 'yes'|'no',
     *                  'plugintype' => 32 or different type
     *                  'includetemplates' => true|false
     * @return array with tags and counts
     */
    public function getTags($minlimit, $filter) {
        $db = $this->_getPluginsDB();
        if(!$db) return array();

        if($filter['showall'] == 'yes') {
            $shown = "1";
        } else {
            $shown = "'" . $this->obsoleteTag . "' NOT IN(SELECT tag FROM plugin_tags WHERE plugin_tags.plugin = B.plugin)
                      AND B.securityissue = ''";
        }
        if($filter['plugintype'] == 32) {
            $shown .= ' AND B.type = 32';
        } elseif(!$filter['includetemplates']) {
            $shown .= ' AND B.type <> 32';
        }

        /** @var $stmt PDOStatement */
        $stmt = $db->prepare(
            "SELECT A.tag, COUNT(A.tag) as cnt
                                FROM plugin_tags as A, plugins as B
                               WHERE A.plugin = B.plugin
                                 AND $shown
                            GROUP BY tag
                              HAVING cnt >= :minlimit
                            ORDER BY cnt DESC"
        );

        $stmt->bindParam(':minlimit', $minlimit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Return number of installations for most popular plugin
     * besides the bundled ones
     * Otherwise 1 is return, to prevent dividing by zero. (and it correspondence with the usage of the author self)
     *
     * @param string $type either 'plugins' or 'templates', '' shows all
     * @return int
     */
    public function getMaxPopularity($type = '') {
        $db = $this->_getPluginsDB();
        if(!$db) return 1;

        $sql = "SELECT A.popularity
                  FROM plugins A
                 WHERE '" . $this->obsoleteTag . "' NOT IN(SELECT tag FROM plugin_tags WHERE plugin_tags.plugin = A.plugin)";


        $sql .= str_repeat("AND plugin != ? ", count($this->bundled));

        if($type == 'plugins' || $type == 'plugin') $sql .= "AND plugin NOT LIKE 'template:%'";
        if($type == 'templates' || $type == 'template') $sql .= "AND plugin LIKE 'template:%'";

        $sql .= "ORDER BY popularity DESC
                 LIMIT 1";

        /** @var $stmt PDOStatement */
        $stmt = $db->prepare($sql);
        $stmt->execute($this->bundled);
        $res = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $retval = $res[0]['popularity'];
        if(!$retval) $retval = 1;
        return (int) $retval;
    }

    /**
     * Delete all information about plugin from repository database
     * (popularity data is left intact)
     *
     * @param string $plugin extension id e.g. pluginname or template:templatename
     */
    public function deletePlugin($plugin) {
        $db = $this->_getPluginsDB();
        if(!$db) return;

        /** @var $stmt PDOStatement */
        $stmt = $db->prepare('DELETE FROM plugins          WHERE plugin = ?');
        $stmt->execute(array($plugin));

        $stmt = $db->prepare('DELETE FROM plugin_tags      WHERE plugin = ?');
        $stmt->execute(array($plugin));

        $stmt = $db->prepare('DELETE FROM plugin_similar   WHERE plugin = ? OR other = ?');
        $stmt->execute(array($plugin, $plugin));

        $stmt = $db->prepare('DELETE FROM plugin_conflicts WHERE plugin = ? OR other = ?');
        $stmt->execute(array($plugin, $plugin));

        $stmt = $db->prepare('DELETE FROM plugin_depends   WHERE plugin = ? OR other = ?');
        $stmt->execute(array($plugin, $plugin));
    }

    /**
     * render internallink to plugin/template, templates identified by having namespace
     *
     * @param $R Doku_Renderer_xhtml
     * @param $plugin string pluginname
     * @param $title string Title of plugin link
     * @return string rendered internallink
     */
    public function pluginlink(&$R, $plugin, $title = null) {
        if(!getNS($plugin)) {
            return $R->internallink(':plugin:'.$plugin, $title, null, true);
        } else {
            if(!$title) $title = noNS($plugin);
            return $R->internallink(':'.$plugin, $title, null, true);
        }
    }

    /**
     * Return array of supported DokuWiki releases
     * only releases mentioned in config are reported
     * 'newest' supported release at [0]
     *
     * @param string $compatible             raw compatibility text
     * @param bool   $onlyCompatibleReleases don't include not-compatible releases
     * @return array
     */
    public function cleanCompat($compatible, $onlyCompatibleReleases = true) {
        if(!$this->dokuReleases) {
            $this->dokuReleases = array();
            $releases           = explode(',', $this->getConf('releases'));
            $releases           = array_map('trim', $releases);
            $releases           = array_filter($releases);
            foreach($releases as $release) {
                list($date, $name) = preg_split('/(\s+"\s*|")/', $release);
                $name                      = strtolower($name);
                $rel                       = array(
                    'date' => $date,
                    'name' => $name
                );
                $rel['label']              = ($name ? '"'.ucwords($name).'"' : '');
                $this->dokuReleases[$date] = $rel;
            }
        }
        preg_match_all('/(!?[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]\+?|!?[a-z A-Z]{4,}\+?)/', $compatible, $matches);
        $matches[0]         = array_map('strtolower', $matches[0]);
        $matches[0]         = array_map('trim', $matches[0]);
        $retval             = array();
        $implicitCompatible = false;
        $nextImplicitCompatible = false;
        $dokuReleases       = $this->dokuReleases;
        ksort($dokuReleases);
        foreach($dokuReleases as $release) {
            $isCompatible = true;
            if(in_array('!' . $release['date'], $matches[0]) || in_array('!' . $release['name'], $matches[0])) {
                $isCompatible = false;
                // stop implicit compatibility
                $nextImplicitCompatible = false;
                $implicitCompatible = false;
            }elseif(in_array($release['date'].'+', $matches[0]) || in_array($release['name'].'+', $matches[0])) {
                $nextImplicitCompatible = true;
            }
            if($nextImplicitCompatible || !$isCompatible || in_array($release['date'], $matches[0]) || in_array($release['name'], $matches[0]) || $implicitCompatible) {
                if(!$onlyCompatibleReleases || $isCompatible) {
                    $retval[$release['date']]['label']    = $release['label'];
                    $retval[$release['date']]['implicit'] = $implicitCompatible;
                    $retval[$release['date']]['isCompatible'] = $isCompatible;
                }
            }
            if($nextImplicitCompatible) {
                $implicitCompatible = true;
            }
        }
        krsort($retval);
        return $retval;
    }

    /**
     * @param bool $addInfolink
     * @return string rendered
     */
    public function renderCompatibilityHelp($addInfolink = false) {
        $infolink = '<sup><a href="http://www.dokuwiki.org/extension_compatibility" title="'.$this->getLang('compatible_with_info').'">?</a></sup>';
        $infolink = $addInfolink ? $infolink : '';
        return sprintf($this->getLang('compatible_with'), $infolink);
    }

    /**
     * Clean list of plugins, return rendered as internallinks
     * input may be comma separated or array
     *
     * @param string|array          $plugins
     * @param Doku_Renderer_xhtml   $R
     * @param string                $sep
     * @return string
     */
    public function listplugins($plugins, $R, $sep = ', ') {
        if(!is_array($plugins)) {
            $plugins = explode(',', $plugins);
        }
        $plugins = array_map('trim', $plugins);
        $plugins = array_map('strtolower', $plugins);
        $plugins = array_unique($plugins);
        $plugins = array_filter($plugins);
        sort($plugins);
        $out = array();
        foreach($plugins as $plugin) {
            $out[] = $this->pluginlink($R, $plugin);
        }
        return join($sep, $out);
    }

    /**
     * Convert comma separated list of tags to filterlinks
     *
     * @param string $string comma separated list of tags
     * @param string $target page id
     * @param string $sep
     * @return string
     */
    public function listtags($string, $target, $sep = ', ') {
        $tags = $this->parsetags($string);
        $out  = array();
        foreach($tags as $tag) {
            $out[] = '<a href="'.wl($target, array('plugintag' => $tag)).'#extension__table" '.
                'class="wikilink1" title="List all plugins with this tag">'.hsc($tag).'</a>';
        }
        return join($sep, $out);
    }

    /**
     * Clean comma separated list of tags, return as sorted array
     *
     * @param string $string comma separated list of tags
     * @return array
     */
    public function parsetags($string) {
        $tags = preg_split('/[;,\s]/', $string);
        $tags = array_map('strtolower', $tags);
        $tags = array_unique($tags);
        $tags = array_filter($tags);
        sort($tags);
        return $tags;
    }

    /**
     * Convert $type (int) to list of filterlinks
     *
     * @param int    $type
     * @param string $target page id
     * @param string $sep
     * @return string
     */
    public function listtype($type, $target, $sep = ', ') {
        $types = array();
        foreach($this->types as $k => $v) {
            if($type & $k) {
                $types[] = '<a href="'.wl($target, array('plugintype' => $k)).'#extension__table" '.
                    'class="wikilink1" title="List all '.$v.' plugins">'.$v.'</a>';
            }
        }
        sort($types);
        return join($sep, $types);
    }

    /**
     * Convert $type (int) to array of names
     *
     * @param int $type
     * @return array
     */
    public function listtypes($type) {
        $types = array();
        foreach($this->types as $k => $v) {
            if($type & $k) $types[] = $v;
        }
        sort($types);
        return $types;
    }

    /**
     * Convert plugin type name (comma sep. string) to (int)
     *
     * @param string $types
     * @return int
     */
    public function parsetype($types) {
        $type = 0;
        foreach($this->types as $k => $v) {
            if(preg_match('#' . preg_quote($v) . '#i', $types)) {
                $type += $k;
            }
        }
        if($type === 0 AND $types === '') {
            $type = 512; // CSS/JS-only
        }
        return $type;
    }

    /**
     * Create tables for repository
     *
     * @param PDO $db
     */
    private function _initPluginDB($db) {
        msg("Repository plugin: data tables created for plugin repository", -1);
        $db->exec('CREATE TABLE plugin_conflicts (plugin varchar(50) NOT NULL, other varchar(50) NOT NULL);');
        $db->exec('CREATE TABLE plugin_depends (plugin varchar(50) NOT NULL, other varchar(50) NOT NULL);');
        $db->exec('CREATE TABLE plugin_similar (plugin varchar(50) NOT NULL, other varchar(50) NOT NULL);');
        $db->exec('CREATE TABLE plugin_tags (plugin varchar(50) NOT NULL, tag varchar(255) NOT NULL);');
        $db->exec(
            'CREATE TABLE plugins (plugin varchar(50) PRIMARY KEY NOT NULL, name varchar(255) default NULL,
                                   description varchar(255) default NULL, author varchar(255) default NULL, email varchar(255) default NULL,
                                   compatible varchar(255) default NULL, lastupdate date default NULL, downloadurl varchar(255) default NULL,
                                   bugtracker varchar(255) default NULL, sourcerepo varchar(255) default NULL, donationurl varchar(255) default NULL, type int(11) NOT NULL default 0,
                                   screenshot varchar(255) default NULL, tags varchar(255) default NULL, securitywarning varchar(255) default NULL, securityissue varchar(255) NOT NULL,
                                   bestcompatible varchar(50) default NULL, popularity int default 0);'
        );
    }

    /**
     * Return security warning with replaced shortcut, if any.
     * If not, return original warning.
     *
     * @param string $warning Original warning content
     * @return string
     */
    public function replaceSecurityWarningShortcut($warning) {
        if(in_array($warning,$this->securitywarning)){
            return $this->getLang('security_'.$warning);
        }
        return hsc($warning);
    }
}

