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

$string['pluginname'] = 'Local Plugin Corolair';
$string['sidepanel'] = 'Positionnement du Tuteur IA à l\'écran';
$string['sidepaneldesc'] = 'Choisissez si vous préférez afficher les Tuteurs IA sur le côté droit des cours sous forme de panneau latéral (recommandé) ou dans le coin inférieur droit comme un chatbot classique.';
$string['true'] = 'Panneau Latéral';
$string['false'] = 'Chatbot';
$string['apikey'] = 'Clé API Corolair';
$string['apikeydesc'] = 'Cette clé est générée lors de l\'installation du plugin. Veuillez la garder secrète. Elle peut être demandée par l\'équipe support de Corolair.';
$string['corolairlogin'] = 'Compte Corolair';
$string['corolairlogindesc'] = 'Le compte Admin Corolair est associé à cet email. Il pourra être demandé par l\'équipe support de Corolair.';
$string['plugininstalledsuccess'] = 'Plugin installé avec succès. Vous pouvez maintenant créer et partager des Tuteurs IA depuis l\'onglet Corolair. Vous pouvez également permettre aux formateurs de créer des Tuteurs IA en leur attribuant le rôle de Corolair Plugin via Utilisateurs > Permissions > Attribuer des rôles système. Si vous rencontrez des problèmes, veuillez contacter l\'équipe Corolair.';
$string['curlerror'] = 'Une erreur est survenue lors de la communication avec l\'API Corolair. Impossible d\'enregistrer votre instance Moodle, veuillez réessayer. Si l\'erreur persiste, veuillez contacter l\'équipe Corolair.';
$string['apikeymissing'] = 'Clé API non trouvée dans la réponse de l\'API Corolair.';
$string['corolair:createtutor'] = 'Permet à l\'utilisateur de créer et gérer des Tuteurs IA dans le plugin Corolair.';
$string['noapikey'] = 'Aucune Clé API Corolair';
$string['errortoken'] = 'Erreur lors de la récupération du token';
$string['missingcapability'] = 'Vous ne pouvez pas accéder à cette page';
$string['roleproblem'] = 'Nous avons rencontré un problème lors de la création ou de l\'attribution du nouveau rôle de Manager Corolair. Vous pouvez toujours le configurer manuellement en ajoutant la capacité "Corolair Local Plugin" à n\'importe quel rôle système. Si vous rencontrez des problèmes, veuillez contacter l\'équipe Corolair via contact@corolair.com.';
$string['coursenodetitle'] = 'Corolair : Créer un Tuteur IA';
$string['frontpagenodetitle'] = 'Corolair';
$string['createtutorcapability'] = 'Exclure les cours sans capacité d\'édition';
$string['createtutorcapabilitydesc'] = 'L\'utilisateur ne pourra créer des Tuteurs IA qu\'à partir des cours qu\'il peut gérer. Si cette option est à Faux, il pourra créer des Tuteurs IA à partir des ressources des cours où il est simplement inscrit.';
$string['capabilitytrue'] = 'Vrai';
$string['capabilityfalse'] = 'Faux';
$string['unexpectederror'] = 'Une erreur inattendue s\'est produite. Veuillez réessayer. Si l\'erreur persiste, veuillez contacter l\'équipe Corolair.';
$string['trainerpage'] = 'Corolair';
$string['nocorolairlogin'] = 'Aucun compte rattaché';
$string['createtutorcapability'] = 'Permet à l\'utilisateur de créer et gérer ses Tuteurs IA avec Corolair';
$string['tokenname'] = 'Jeton REST Corolair';
$string['rolename'] = 'Manager Corolair';
$string['roledescription'] = 'Rôle pour la gestion des Tuteurs IA dans Corolair';
$string['privacy:metadata:corolair'] = 'Les métadonnées envoyées à Corolair permettent un accès transparent à vos données sur le système distant.';
$string['privacy:metadata:corolair:userid'] = 'L\'identifiant de l\'utilisateur est envoyé pour vous identifier de manière unique sur Corolair';
$string['privacy:metadata:corolair:useremail'] = 'Votre adresse e-mail est envoyée pour vous identifier de manière unique sur Corolair et anticiper de potentielles communication ultérieure';
$string['privacy:metadata:corolair:userfirstname'] = 'Votre prénom est envoyé pour personnaliser votre expérience sur Corolair et identifier vos conversations pour votre formateur';
$string['privacy:metadata:corolair:userlastname'] = 'Votre nom de famille est envoyé pour personnaliser votre expérience sur Corolair et identifier vos conversations pour votre formateur';
$string['privacy:metadata:corolair:userrolename'] = 'Votre rôle est envoyé pour gérer vos permissions sur Corolair';
$string['privacy:metadata:corolair:interaction'] = 'Les enregistrements de vos interactions, tels que les tuteurs créés et les conversations, sont envoyés pour améliorer votre expérience';
$string['localhosterror'] = 'Impossible d\'enregistrer l\'instance Moodle avec Corolair car le site fonctionne en localhost.';
$string['webservicesenableerror'] = 'Impossible d\'activer les services web.';
$string['restprotocolenableerror'] = 'Impossible d\'activer le protocole REST.';
$string['servicecreationerror'] = 'Impossible de créer le service REST Corolair.';
$string['capabilityassignerror'] = 'Impossible d\'attribuer la capacité "{$a}" au rôle.';
$string['tokencreationerror'] = 'Impossible de créer le jeton REST Corolair.';
$string['installtroubleshoot'] = 'Si vous rencontrez des problèmes lors de l\'installation, veuillez vous référer au <a href="https://corolair.notion.site/Corolair-Local-Plugin-Installation-Troubleshoot-1744d042d295805ca045d274b413a328"> guide de dépannage manuel</a>';
$string['adhocqueued'] = 'La synchronisation avec les services Corolair aurait dû commencer dans votre tâche ad hoc <a href="{$a->adhoc_link}">\local_corolair\task\setup_corolair_connection_task</a>. Si ce n\'est pas le cas, déclenchez une génération de clé API depuis <a href="{$a->trainer_page_link}">ici</a>.';
$string['corolairtuto'] = 'Apprenez à utiliser Corolair en consultant <a href="https://corolair.notion.site/Corolair-Local-Plugin-Installation-Troubleshoot-1744d042d295805ca045d274b413a328">ce tutoriel</a>.';
