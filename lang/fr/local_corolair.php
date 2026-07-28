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

$string['adhocqueued'] = 'La synchronisation avec les services Raison aurait dû commencer dans votre tâche ad hoc <a href="{$a->adhoc_link}">\local_corolair\task\setup_corolair_connection_task</a>. Si ce n\'est pas le cas, déclenchez une génération de clé API depuis <a href="{$a->trainer_page_link}">ici</a>.';
$string['apikey'] = 'Clé API Raison';
$string['apikeydesc'] = 'Cette clé est générée lors de l\'installation du plugin. Veuillez la garder secrète. Elle peut être demandée par l\'équipe support de Raison.';
$string['apikeymissing'] = 'Clé API non trouvée dans la réponse de l\'API Raison.';
$string['apikeyset'] = 'La clé API est définie, veuillez recharger la page';
$string['calendlydemo'] = 'Pour que nous puissions vous aider au mieux, nous vous invitons à nous présenter votre cas d\'usage lors d\'un appel découverte avec l\'équipe Raison. Après cela, nos développeurs pourront se concentrer sur la résolution des problèmes de connexion avec votre instance Moodle. Vous pouvez réserver un échange <strong> <a href="https://discoverycall.raison.is/" target="_blank">ici</a> </strong>.';
$string['capabilityassignerror'] = 'Impossible d\'attribuer la capacité "{$a}" au rôle.';
$string['capabilityfalse'] = 'Faux';
$string['capabilitytrue'] = 'Vrai';
$string['corolair:createtutor'] = 'Permet à l\'utilisateur de créer et gérer des Tuteurs IA dans le plugin Raison.';
$string['corolair:viewroles'] = 'Permet à l\'utilisateur de récupérer les métadonnées des rôles Moodle pour l\'intégration Raison.';
$string['coursenodetitle'] = 'Assistant IA de Raison';
$string['createtutorcapability'] = 'Permet à l\'utilisateur de créer et gérer ses Tuteurs IA avec Raison';
$string['createtutorcapabilitydesc'] = 'L\'utilisateur ne pourra créer des Tuteurs IA qu\'à partir des cours qu\'il peut gérer. Si cette option est à Faux, il pourra créer des Tuteurs IA à partir des ressources des cours où il est simplement inscrit.';
$string['curlerror'] = 'Une erreur est survenue lors de la communication avec l\'API Raison. Impossible d\'enregistrer votre instance Moodle, veuillez réessayer. Si l\'erreur persiste, veuillez contacter l\'équipe Raison.';
$string['errortoken'] = 'Erreur lors de la récupération du token';
$string['invalidredirecturl'] = 'Corolair a renvoyé une destination de redirection non fiable.';
$string['eventprivacydeletioncompleted'] = 'Suppression des données privées Raison terminée';
$string['eventremoterequestcompleted'] = 'Requête distante Raison terminée';
$string['eventwebservicetokenlifecycle'] = 'Cycle de vie du jeton de service web Corolair mis à jour';
$string['excludedmods'] = 'Activités exclues';
$string['excludedmodsdesc'] = 'Utilisez cette liste pour désactiver les assistants dans certains types d\'activités, par exemple afin d\'empêcher les étudiants de les utiliser pendant une évaluation. Listez les noms courts des activités, séparés par des virgules (ex. : "quiz, assign"). Le nom court correspond au dossier visible dans l\'URL de l\'activité après \'/mod/\' (ex : \'/mod/quiz/\' → \'quiz\'). Cela marche aussi avec des plugins d\'activités externes.';
$string['false'] = 'Chatbot';
$string['frontpagenodetitle'] = 'Raison';
$string['installtroubleshoot'] = 'Si vous rencontrez des problèmes lors de l\'installation, veuillez vous référer au <a href="https://troubleshoot-moodle.raison.is" target="_blank"> guide de dépannage manuel</a>';
$string['localhosterror'] = 'Impossible d\'enregistrer l\'instance Moodle avec Raison car le site fonctionne en localhost.';
$string['missingcapability'] = 'Vous ne pouvez pas accéder à cette page';
$string['noapikey'] = 'Aucune Clé API Raison';
$string['noraisonlogin'] = 'Aucun compte rattaché';
$string['pluginname'] = 'Local Plugin Raison';
$string['privacy:metadata:raison'] = 'Les métadonnées envoyées à Raison permettent un accès transparent à vos données sur le système distant.';
$string['privacy:metadata:raison:interaction'] = 'Les enregistrements de vos interactions, tels que les tuteurs créés et les conversations, sont envoyés pour améliorer votre expérience';
$string['privacy:metadata:raison:useremail'] = 'Votre adresse e-mail est envoyée pour vous identifier de manière unique sur Raison et anticiper de potentielles communication ultérieure';
$string['privacy:metadata:raison:userfirstname'] = 'Votre prénom est envoyé pour personnaliser votre expérience sur Raison et identifier vos conversations pour votre formateur';
$string['privacy:metadata:raison:userid'] = 'L\'identifiant de l\'utilisateur est envoyé pour vous identifier de manière unique sur Raison';
$string['privacy:metadata:raison:userlastname'] = 'Votre nom de famille est envoyé pour personnaliser votre expérience sur Raison et identifier vos conversations pour votre formateur';
$string['privacy:metadata:raison:userrolename'] = 'Votre rôle est envoyé pour gérer vos permissions sur Raison';
$string['raisonlogin'] = 'Compte Raison';
$string['raisonlogindesc'] = 'Le compte Admin Raison est associé à cet email. Il pourra être demandé par l\'équipe support de Raison.';
$string['raisontuto'] = 'Apprenez à utiliser Raison en consultant <a href="https://troubleshoot-moodle.raison.is" target="_blank">ce tutoriel</a>.';
$string['redirectingmessage'] = 'Si vous n\'êtes pas redirigé automatiquement, veuillez cliquer sur le bouton ci-dessous pour continuer vers Raison.';
$string['restprotocolenableerror'] = 'Impossible d\'activer le protocole REST.';
$string['retryregistration'] = 'Réessayer l\'enregistrement Raison';
$string['roledescription'] = 'Rôle pour la gestion des Tuteurs IA dans Raison';
$string['rolename'] = 'Manager Raison';
$string['roleproblem'] = 'Nous avons rencontré un problème lors de la création ou de l\'attribution du nouveau rôle de Manager Raison. Vous pouvez toujours le configurer manuellement en ajoutant la capacité "Raison Local Plugin" à n\'importe quel rôle système. Si vous rencontrez des problèmes, veuillez contacter l\'équipe Raison via contact@raison.is.';
$string['servicecreationerror'] = 'Impossible de créer le service REST Raison.';
$string['setupaction'] = 'Ouvrir la configuration Corolair';
$string['setupchangeregistration'] = 'Un jeton de service web Moodle sera créé et envoyé à Corolair afin d\'enregistrer ce site.';
$string['setupchangerest'] = 'Le protocole REST sera ajouté sans modifier les autres protocoles déjà activés.';
$string['setupchangewebservices'] = 'Les services web Moodle seront activés s\'ils sont actuellement désactivés.';
$string['setupconfirmbutton'] = 'Activer les services web et REST';
$string['setupconfirmquestion'] = 'Acceptez-vous ces modifications globales et le démarrage de l\'enregistrement Corolair ?';
$string['setupcontinuebutton'] = 'Démarrer l\'enregistrement Corolair';
$string['setupcontinuequestion'] = 'Démarrer l\'enregistrement Corolair maintenant ?';
$string['setupconsentdescription'] = 'Corolair est actuellement inactif. Son activation apporte les modifications globales suivantes :';
$string['setupconsentheading'] = 'Vérifier et approuver l\'activation de Corolair';
$string['setupconsentmissing'] = 'L\'enregistrement Corolair ne peut pas s\'exécuter sans le consentement enregistré d\'un administrateur.';
$string['setupcurrentstatus'] = 'État actuel — Services web Moodle : {$a->webservices} ; REST : {$a->rest}.';
$string['setuppagetitle'] = 'Configuration de Corolair';
$string['setupqueued'] = 'Votre consentement a été enregistré et l\'enregistrement Corolair a été mis en file d\'attente.';
$string['setupqueuedwithoutconsent'] = 'L\'enregistrement Corolair a été mis en file d\'attente. Aucun paramètre global de service web n\'a été modifié.';
$string['setupreadynotification'] = 'Corolair a été installé. Les services web Moodle et REST sont déjà activés ; aucun consentement d\'activation n\'est nécessaire. Un administrateur peut <a href="{$a}">démarrer l\'enregistrement Corolair</a>.';
$string['setupreadydescription'] = 'Les services web Moodle et REST sont déjà activés. Aucun consentement d\'activation n\'est requis et ces paramètres ne seront pas modifiés.';
$string['setupreadyheading'] = 'Les prérequis des services web sont déjà activés';
$string['setuprequirednotification'] = 'Corolair est installé mais reste inactif. Un administrateur du site doit <a href="{$a}">vérifier et approuver les modifications requises des services web</a>.';
$string['setupstatus'] = 'État de l\'intégration';
$string['setupstatuscomplete'] = 'Connecté. Le consentement administrateur est enregistré et l\'enregistrement Corolair est terminé.';
$string['setupstatuspending'] = 'Le consentement administrateur est enregistré et l\'enregistrement est en attente. {$a}';
$string['setupstatusrequired'] = 'La configuration nécessite le consentement d\'un administrateur. {$a}';
$string['setupstatusready'] = 'Les services web et REST sont déjà activés. Aucun consentement d\'activation n\'est nécessaire ; démarrez l\'enregistrement. {$a}';
$string['sidepanel'] = 'Positionnement du Tuteur IA à l\'écran';
$string['sidepaneldesc'] = 'Choisissez si vous préférez afficher les Tuteurs IA sur le côté droit des cours sous forme de panneau latéral (recommandé) ou dans le coin inférieur droit comme un chatbot classique.';
$string['tokencreationerror'] = 'Impossible de créer le jeton REST Raison.';
$string['tokenexpirywarningbody'] = 'Corolair n\'a pas pu renouveler son jeton de service web Moodle. Le jeton actuel expire le {$a->expiry}. Code d\'erreur sûr : {$a->error}. Vérifiez le cron et la connectivité dans les paramètres Corolair.';
$string['tokenexpirywarningsubject'] = 'Le renouvellement du jeton Corolair nécessite votre attention';
$string['tokenmissing'] = 'Le jeton de service web Corolair actuel est introuvable.';
$string['tokenname'] = 'Jeton REST Raison';
$string['tokenrotationrequestfailed'] = 'La demande de renouvellement du jeton Corolair a échoué.';
$string['tokenrotationresponseinvalid'] = 'Corolair a renvoyé un accusé de réception de renouvellement invalide.';
$string['tokenrotationretry'] = 'Réessayer le renouvellement du jeton';
$string['tokenrotationretryconfirm'] = 'Mettre en file d\'attente une nouvelle tentative immédiate avec le jeton candidat et l\'identifiant de rotation existants ?';
$string['tokenrotationretryqueued'] = 'La nouvelle tentative de renouvellement du jeton Corolair a été mise en file d\'attente.';
$string['tokenrotationstatusfailed'] = 'Le renouvellement du jeton n\'a pas abouti. Le jeton actuel expire le {$a->expiry}. Code d\'erreur sûr : {$a->error}. Moodle réessaiera automatiquement. <a href="{$a->retryurl}">Réessayer maintenant</a>.';
$string['taskrotatewebservicetoken'] = 'Renouveler le jeton de service web Moodle de Corolair';
$string['trainerpage'] = 'Raison';
$string['true'] = 'Panneau Latéral';
$string['unexpectederror'] = 'Une erreur inattendue s\'est produite. Veuillez réessayer. Si l\'erreur persiste, veuillez contacter l\'équipe Raison.';
$string['viewrolescapability'] = 'Permet aux utilisateurs de récupérer les rôles Moodle via le service web Corolair';
$string['webservicesenableerror'] = 'Impossible d\'activer les services web.';
