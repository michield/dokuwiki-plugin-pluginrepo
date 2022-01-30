<?php

/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 *
 * @author Schplurtz le Déboulonné <Schplurtz@laposte.net>
 * @author Antoine Turmel <geekshadow@gmail.com>
 * @author NicolasFriedli <nicolas@theologique.ch>
 * @author Fabrice Dejaigher <fabrice@chtiland.com>
 */
$lang['by']                    = 'de';
$lang['last_updated_on']       = 'Dernière mise à jour';
$lang['provides']              = 'Fournit';
$lang['compatible_with']       = 'Compatible avec DokuWiki%s';
$lang['compatible_with_info']  = 'Merci de mettre à jour ce champ';
$lang['no_compatibility']      = 'Pas d\'informations de compatibilité disponible !';
$lang['compatible_unknown']    = 'inconnu';
$lang['compatible_yes']        = 'oui';
$lang['compatible_no']         = 'no';
$lang['compatible_probably']   = 'probablement';
$lang['develonly']             = 'Développement seulement';
$lang['conflicts_with']        = 'En conflit avec';
$lang['requires']              = 'Nécessite';
$lang['similar_to']            = 'Similaire à';
$lang['tagged_with']           = 'étiquettes :';
$lang['needed_for']            = 'Requis par';
$lang['securitywarning']       = 'Avertissement de sécurité (merci de lire %s)&nbsp;:';
$lang['security_informationleak'] = 'Ce greffon divulgue des informations qui pourraient être utiles à un pirate. Il n\'est pas recommandé dans une installation publique.';
$lang['security_allowsscript'] = 'Ce greffon permet l\'exécution de scripts. Il ne doit être utilisé que si vous faites confiance à TOUS les éditeurs ; mieux adapté aux wikis personnels privés.';
$lang['security_requirespatch'] = 'Ce greffon nécessite de patcher le noyau de DokuWiki. Les patchs manuels peuvent casser la compatibilité avec d\'autres greffons et rendent plus difficile la sécurisation de votre installation par les mises à jour vers la dernière version.';
$lang['security_partlyhidden'] = 'Masquer des parties d\'une page de DokuWiki n\'est pas pris en charge par le noyau. La plupart des tentatives d\'introduire un contrôle ACL pour des parties d\'une page transmettent des informations via le flux RSS, la recherche ou une autre des fonctionnalités du noyau.';
$lang['securityissue']         = 'Le problème de sécurité suivant a été rapporté pour ce greffon&nbsp;:';
$lang['securityrecommendation'] = 'Il n\'est pas recommandé d\'utiliser ce greffon jusqu\'à ce que le problème soit résolu. Les auteurs de greffons devraient lire les %s';
$lang['securitylink']          = 'consignes de sécurité des greffons';
$lang['name_underscore']       = 'Le nom du greffon contient des «&nbsp;souligné&nbsp;», il ne génèrera pas de points de popularité.';
$lang['name_oldage']           = 'Cette extension n\'a pas été mise à jour par ses developpeurs depuis plus de deux ans. Elle pourrait ne plus être maintenue ou comporter des problèmes de compatibilité.';
$lang['extension_obsoleted']   = '<strong>Cette extension est marquée comme obsolète.</strong> Par conséquent, elle est cachée dans le gestionnaire d\'extensions et la liste des extensions. De plus, elle est candidate à suppression.';
$lang['missing_downloadurl']   = 'L\'absence de lien de téléchargement signifie qu\'on ne peut pas installer cette extension via le gestionnaire d\'extensions. Veuillez vous reporter à <a href="/fr:devel:plugins#publication_d_extensions_sur_le_site_dokuwikiorg" class="wikilink1" title="fr:devel:plugins">Publication d\'extensions sur le site dokuwiki.org</a>. On recommande les hébergeurs de dépôts publics tel que GitHub, GitLab ou Bitbucket.';
$lang['wrongnamespace']        = 'Cette extension ne se trouve ni dans la catégorie «plugin» ni dans la catégorie «template» et est par conséquent ignorée.';
$lang['downloadurl']           = 'Télécharger';
$lang['bugtracker']            = 'Rapporter des bugs';
$lang['sourcerepo']            = 'Dépôt';
$lang['source']                = 'Source';
$lang['donationurl']           = 'Faire un don';
$lang['more_extensions']       = 'et %d de plus';
$lang['t_search_plugins']      = 'Rechercher des greffons';
$lang['t_search_template']     = 'Rechercher des thèmes';
$lang['t_searchintro_plugins'] = 'Filtrer les greffons disponibles par type ou en utilisant le nuage d\'étiquettes. Vous pouvez aussi rechercher dans la catégorie des greffons, \'plugin\', en utilisant la boîte de recherche.';
$lang['t_searchintro_template'] = 'Filtrer les thèmes disponibles en utilisant le nuage d\'étiquettes. Vous pouvez aussi rechercher dans la catégorie des thèmes, \'template\', en utilisant la boite de recherche.';
$lang['t_btn_search']          = 'Recherche';
$lang['t_btn_searchtip']       = 'Rechercher dans la catégorie';
$lang['t_filterbytype']        = 'Filtrer par type';
$lang['t_typesyntax']          = 'Les greffons %s étendent la syntaxe basique de DokuWiki.';
$lang['t_typeaction']          = 'Les greffons %s remplacent ou étendent les fonctionnalités du noyau de DokuWiki.';
$lang['t_typeadmin']           = 'Les greffons %s fournissent des outils d\'administration supplémentaires.';
$lang['t_typerender']          = 'Les greffons %s ajoutent de nouveaux modes d\'exportation ou remplacent le moteur de rendu XHTML de base.';
$lang['t_typehelper']          = 'Les greffons %s fournissent des fonctionnalités partagées par d\'autres greffons.';
$lang['t_typetemplate']        = 'Les %s changent l\'apparence et le comportement de DokuWiki.';
$lang['t_typeremote']          = 'Les greffons %s ajoutent des méthodes à l\'API distante accessible via des web services.';
$lang['t_typeauth']            = 'les greffons %s ajoutent des modules d\'authentification.';
$lang['t_typecli']             = 'Les greffons %s ajoutent des commandes à la ligne de commande';
$lang['t_filterbytag']         = 'Filtrer par étiquette';
$lang['t_availabletype']       = 'Greffons %s disponibles';
$lang['t_availabletagged']     = 'Étiquetés \'%s\'';
$lang['t_availableplugins']    = 'Tous disponibles';
$lang['t_jumptoplugins']       = 'Aller au premier greffon commençant par&nbsp;:';
$lang['t_resetfilter']         = 'Tout afficher (enlever filtre/tri)';
$lang['t_oldercompatibility']  = 'Compatible avec les versions plus anciennes de DokuWiki';
$lang['t_name_plugins']        = 'Greffon';
$lang['t_name_template']       = 'Thème';
$lang['t_sortname']            = 'Trier par nom';
$lang['t_description']         = 'Description';
$lang['t_author']              = 'Auteur';
$lang['t_sortauthor']          = 'Trier par auteur';
$lang['t_type']                = 'Type';
$lang['t_sorttype']            = 'Trier par type';
$lang['t_date']                = 'Dernière mise à jour';
$lang['t_sortdate']            = 'Trier par date';
$lang['t_popularity']          = 'Popularité';
$lang['t_sortpopularity']      = 'Trier par popularité';
$lang['t_compatible']          = 'Dernière compatible';
$lang['t_sortcompatible']      = 'Trier par compatibilité';
$lang['t_screenshot']          = 'Capture d\'écran';
$lang['t_download']            = 'Télécharger';
$lang['t_provides']            = 'Fournit';
$lang['t_tags']                = 'Étiquettes';
$lang['t_bundled']             = 'inclus par défaut';
$lang['screenshot_title']      = 'Aperçu de %s';
