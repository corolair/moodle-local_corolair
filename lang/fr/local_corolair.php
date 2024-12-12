<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Language strings for the Corolair Local Plugin.
 *
 * @package   local_corolair
 * @copyright  2024 Corolair 
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Plugin Local Corolair';
$string['sidepanel'] = 'Position du Tuteur IA sur l\'écran';
$string['sidepaneldesc'] = 'Choisissez si vous préférez afficher les Tuteurs IA dans le panneau latéral droit des cours (recommandé) ou dans le coin inférieur droit comme un chatbot classique.';
$string['true'] = 'Panneau Latéral';
$string['false'] = 'Chatbot';
$string['apikey'] = 'Clé API Corolair';
$string['apikeydesc'] = 'Cette clé est générée lors de l\'installation du plugin. Veuillez la garder secrète. Elle peut être demandée par l\'équipe de support Corolair.';
$string['corolairlogin'] = 'Compte Corolair';
$string['corolairlogindesc'] = 'Le compte principal Corolair est associé à cet email. Il peut être demandé par l\'équipe de support Corolair.';
$string['plugininstalledsuccess'] = 'Plugin installé avec succès. Vous pouvez maintenant créer et partager des Tuteurs IA depuis l\'onglet Corolair. Vous pouvez également permettre aux enseignants/formateurs de créer des Tuteurs IA en leur attribuant le rôle de Manager Corolair depuis Utilisateurs > Permissions > Assigner les rôles système. Si vous rencontrez des problèmes, veuillez contacter l\'équipe Corolair.';
$string['curlerror'] = 'Une erreur s\'est produite lors de la communication avec l\'API Corolair. Impossible d\'enregistrer votre instance Moodle, veuillez réessayer. Si l\'erreur persiste, veuillez contacter l\'équipe Corolair.';
$string['apikeymissing'] = 'Clé API non trouvée dans la réponse de l\'API Corolair.';
$string['servicecreationfailed'] = 'Échec de la création du service REST Corolair.';
$string['corolair:createtutor'] = 'Permet à l\'utilisateur de créer et gérer des tuteurs dans le plugin Corolair.';
$string['noapikey'] = 'Pas de clé API Corolair';
$string['errortoken'] = 'Erreur lors de l\'obtention du jeton';
$string['missingcapability'] = 'Pas de permission pour accéder à cette page';
$string['roleproblem'] = 'Nous avons rencontré un problème lors de la création ou de l\'attribution du nouveau rôle Manager Corolair. Vous pouvez toujours le configurer manuellement en autorisant la capacité "Plugin Local Corolair" à n\'importe quel rôle système. Si vous rencontrez des problèmes, veuillez contacter l\'équipe Corolair via contact@corolair.com.';
$string['coursenodetitle'] = 'Corolair : Créer un Tuteur IA';
$string['frontpagenodetitle'] = 'Corolair';
$string['createtutorcapability'] = 'Exclure les cours sans capacité d\'édition';
$string['createtutorcapabilitydesc'] = 'L\'utilisateur ne pourra pas créer de Tuteurs IA depuis les cours qu\'il ne peut pas gérer. Si défini sur Faux, il peut créer des Tuteurs IA depuis les cours où il est simplement inscrit.';
$string['capabilitytrue'] = 'Vrai';
$string['capabilityfalse'] = 'Faux';
$string['unexpectederror'] = 'Une erreur inattendue s\'est produite. Veuillez réessayer. Si l\'erreur persiste, veuillez contacter l\'équipe Corolair.';
$string['trainerpage'] = 'Corolair';
$string['nocorolairlogin'] = 'Pas de connexion Corolair';
$string['createtutorcapability'] = 'Permet aux utilisateurs de créer et gérer des tuteurs dans le plugin Corolair';
$string['servicename'] = 'Service REST Corolair';
$string['tokenname'] = 'Jeton REST Corolair';
$string['rolename'] = 'Manager Corolair';
$string['roledescription'] = 'Rôle pour la gestion des Tuteurs IA Corolair';

