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
 * Language strings for the Raison Local Plugin.
 *
 * @package   local_corolair
 * @copyright  2025 Raison
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Local Plugin Raison';
$string['sidepanel'] = 'Positionnement du Tuteur IA à l\'écran';
$string['sidepaneldesc'] = 'Choisissez si vous préférez afficher les Tuteurs IA sur le côté droit des cours sous forme de panneau latéral (recommandé) ou dans le coin inférieur droit comme un chatbot classique.';
$string['true'] = 'Panneau Latéral';
$string['false'] = 'Chatbot';
$string['apikey'] = 'Clé API Raison';
$string['apikeydesc'] = 'Cette clé est générée lors de l\'installation du plugin. Veuillez la garder secrète. Elle peut être demandée par l\'équipe support de Raison.';
$string['raisonlogin'] = 'Compte Raison';
$string['raisonlogindesc'] = 'Le compte Admin Raison est associé à cet email. Il pourra être demandé par l\'équipe support de Raison.';
$string['curlerror'] = 'Une erreur est survenue lors de la communication avec l\'API Raison. Impossible d\'enregistrer votre instance Moodle, veuillez réessayer. Si l\'erreur persiste, veuillez contacter l\'équipe Raison.';
$string['apikeymissing'] = 'Clé API non trouvée dans la réponse de l\'API Raison.';
$string['corolair:createtutor'] = 'Permet à l\'utilisateur de créer et gérer des Tuteurs IA dans le plugin Raison.';
$string['noapikey'] = 'Aucune Clé API Raison';
$string['errortoken'] = 'Erreur lors de la récupération du token';
$string['missingcapability'] = 'Vous ne pouvez pas accéder à cette page';
$string['roleproblem'] = 'Nous avons rencontré un problème lors de la création ou de l\'attribution du nouveau rôle de Manager Raison. Vous pouvez toujours le configurer manuellement en ajoutant la capacité "Raison Local Plugin" à n\'importe quel rôle système. Si vous rencontrez des problèmes, veuillez contacter l\'équipe Raison via contact@raison.is.';
$string['coursenodetitle'] = 'Assistant IA de Raison';
$string['frontpagenodetitle'] = 'Raison';
$string['createtutorcapabilitydesc'] = 'L\'utilisateur ne pourra créer des Tuteurs IA qu\'à partir des cours qu\'il peut gérer. Si cette option est à Faux, il pourra créer des Tuteurs IA à partir des ressources des cours où il est simplement inscrit.';
$string['capabilitytrue'] = 'Vrai';
$string['capabilityfalse'] = 'Faux';
$string['unexpectederror'] = 'Une erreur inattendue s\'est produite. Veuillez réessayer. Si l\'erreur persiste, veuillez contacter l\'équipe Raison.';
$string['trainerpage'] = 'Raison';
$string['noraisonlogin'] = 'Aucun compte rattaché';
$string['createtutorcapability'] = 'Permet à l\'utilisateur de créer et gérer ses Tuteurs IA avec Raison';
$string['tokenname'] = 'Jeton REST Raison';
$string['rolename'] = 'Manager Raison';
$string['roledescription'] = 'Rôle pour la gestion des Tuteurs IA dans Raison';
$string['privacy:metadata:raison'] = 'Les métadonnées envoyées à Raison permettent un accès transparent à vos données sur le système distant.';
$string['privacy:metadata:raison:userid'] = 'L\'identifiant de l\'utilisateur est envoyé pour vous identifier de manière unique sur Raison';
$string['privacy:metadata:raison:useremail'] = 'Votre adresse e-mail est envoyée pour vous identifier de manière unique sur Raison et anticiper de potentielles communication ultérieure';
$string['privacy:metadata:raison:userfirstname'] = 'Votre prénom est envoyé pour personnaliser votre expérience sur Raison et identifier vos conversations pour votre formateur';
$string['privacy:metadata:raison:userlastname'] = 'Votre nom de famille est envoyé pour personnaliser votre expérience sur Raison et identifier vos conversations pour votre formateur';
$string['privacy:metadata:raison:userrolename'] = 'Votre rôle est envoyé pour gérer vos permissions sur Raison';
$string['privacy:metadata:raison:interaction'] = 'Les enregistrements de vos interactions, tels que les tuteurs créés et les conversations, sont envoyés pour améliorer votre expérience';
$string['localhosterror'] = 'Impossible d\'enregistrer l\'instance Moodle avec Raison car le site fonctionne en localhost.';
$string['webservicesenableerror'] = 'Impossible d\'activer les services web.';
$string['restprotocolenableerror'] = 'Impossible d\'activer le protocole REST.';
$string['servicecreationerror'] = 'Impossible de créer le service REST Raison.';
$string['capabilityassignerror'] = 'Impossible d\'attribuer la capacité "{$a}" au rôle.';
$string['tokencreationerror'] = 'Impossible de créer le jeton REST Raison.';
$string['installtroubleshoot'] = 'Si vous rencontrez des problèmes lors de l\'installation, veuillez vous référer au <a href="https://raison.notion.site/Moodle-Integration-EN-5d5dc1e61f8d4bd89372a6b8009ec4e4?pvs=4" target="_blank"> guide de dépannage manuel</a>';
$string['adhocqueued'] = 'La synchronisation avec les services Raison aurait dû commencer dans votre tâche ad hoc <a href="{$a->adhoc_link}">\local_corolair\task\setup_corolair_connection_task</a>. Si ce n\'est pas le cas, déclenchez une génération de clé API depuis <a href="{$a->trainer_page_link}">ici</a>.';
$string['raisontuto'] = 'Apprenez à utiliser Raison en consultant <a href="https://raison.notion.site/Moodle-Integration-EN-5d5dc1e61f8d4bd89372a6b8009ec4e4?pvs=4" target="_blank">ce tutoriel</a>.';
$string['redirectingmessage'] = 'Si vous n\'êtes pas redirigé automatiquement, veuillez cliquer sur le bouton ci-dessous pour continuer vers Raison.';
$string['calendlydemo'] = 'Pour que nous puissions vous aider au mieux, nous vous invitons à nous présenter votre cas d\'usage lors d\'un appel découverte avec l\'équipe Raison. Après cela, nos développeurs pourront se concentrer sur la résolution des problèmes de connexion avec votre instance Moodle. Vous pouvez réserver un échange <strong> <a href="http://discoverycall.raison.is/" target="_blank">ici</a> </strong>.';
