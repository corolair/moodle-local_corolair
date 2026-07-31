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
$string['disclosureaccess'] = 'Accès';
$string['disclosureaccessread'] = 'Lecture';
$string['disclosureaccesswrite'] = 'Écriture';
$string['disclosureacknowledgebutton'] = 'Je reconnais avoir pris connaissance de cette information';
$string['disclosureacknowledgmentnote'] = 'Cette reconnaissance confirme que vous avez examiné le périmètre de l’intégration. Elle ne constitue pas un consentement juridique.';
$string['disclosureallowlist'] = 'Le jeton est limité à la liste fixe de fonctions du service corolair_rest. Il ne peut invoquer aucune fonction de service web Moodle hors de cette liste.';
$string['disclosurecapabilitiesheading'] = 'Pourquoi des capacités Moodle sont nécessaires';
$string['disclosurecapabilitiesintro'] = 'Moodle vérifie les capacités du propriétaire du jeton ainsi que la liste du service. Certaines capacités évoquant une écriture servent de filtres aux fonctions de lecture du cœur pour retourner des champs complets ou masqués. Le propriétaire du jeton est un administrateur et possède donc d’autres capacités que celles indiquées ci-dessous.';
$string['disclosurecapability'] = 'Capacité';
$string['disclosurecapcompletion'] = 'Autorise les lectures de progression prévues pour de futurs tutorats et analyses adaptatifs. Ces données ne sont pas traitées actuellement.';
$string['disclosurecapcontent'] = 'Permet de lire le contenu des cours et activités, y compris le contenu complet ou masqué lorsque Moodle utilise course:update comme condition de visibilité.';
$string['disclosurecapcoursevisibility'] = 'Permet de lire la structure des cours et catégories, y compris les éléments masqués, comme sources et contexte d’organisation des tuteurs.';
$string['disclosurecapexam'] = 'Permet au flux spécifique de placement d’examen de créer et gérer des activités LTI.';
$string['disclosurecapidentity'] = 'Permet de lire l’identité et l’adresse électronique pour l’association des comptes, les invitations, la personnalisation et le contrôle d’accès. user:update sert de condition de lecture dans les fonctions listées.';
$string['disclosurecapparticipants'] = 'Permet de lire les participants et le contenu de tous les groupes afin de définir correctement les accès.';
$string['disclosurecaproleassign'] = 'Permet d’attribuer le rôle Manager Raison à un formateur invité depuis Raison.';
$string['disclosuredataaccess'] = 'Les inscriptions, rôles, groupes et relations entre participants servent à déterminer l’accès à chaque tuteur.';
$string['disclosuredatacompletion'] = 'La progression des activités et cours est réservée à une fonctionnalité adaptative future et n’est pas traitée actuellement.';
$string['disclosuredatacourse'] = 'Les catégories, structures, sections, ressources, leçons, SCORM et métadonnées LTI servent de sources et de contexte aux tuteurs.';
$string['disclosuredataexam'] = 'Lorsque le placement d’examen est utilisé, les informations LTI servent à créer, modifier ou supprimer le placement demandé.';
$string['disclosuredataheading'] = 'Utilisation des données personnelles et pédagogiques';
$string['disclosuredataidentity'] = 'Les identifiants, noms, adresses électroniques et rôles servent au provisionnement, aux invitations, à l’association des identités, à la personnalisation et aux sessions.';
$string['disclosuredatasite'] = 'L’URL et le nom du site Moodle ainsi que l’identité du service identifient et enregistrent cette installation.';
$string['disclosurefiletransfer'] = 'Le téléchargement entrant et sortant de fichiers est activé pour transférer les ressources de cours prises en charge et les fichiers propres aux fonctionnalités.';
$string['disclosurefunction'] = 'Fonction de service web';
$string['disclosurefunctionsheading'] = 'Liste fixe des fonctions de service web';
$string['disclosurefunctionsintro'] = 'Le plugin standardisé expose les 26 fonctions suivantes. Développez chaque groupe pour voir les noms exacts et leur type d’accès.';
$string['disclosuregroupcompletion'] = 'Lectures de progression';
$string['disclosuregroupcompletiondesc'] = 'Lire la progression des activités et cours pour une future adaptation des tuteurs. Ces données ne sont pas traitées actuellement.';
$string['disclosuregroupcontent'] = 'Lectures des cours et contenus pédagogiques';
$string['disclosuregroupcontentdesc'] = 'Lire les cours, sections, ressources, leçons, paquets SCORM et métadonnées LTI utilisés pour construire et organiser les tuteurs.';
$string['disclosuregroupenrolment'] = 'Lectures des inscriptions, participants et rôles';
$string['disclosuregroupenrolmentdesc'] = 'Lire les membres, participants, capacités et rôles Moodle servant à définir l’accès aux tuteurs.';
$string['disclosuregroupexamplacement'] = 'Écritures de placement d’examen et d’activités';
$string['disclosuregroupexamplacementdesc'] = 'Écritures propres à la fonctionnalité, utilisées uniquement lorsqu’un administrateur lance le placement d’examen.';
$string['disclosuregroupidentity'] = 'Lectures du site et des identités';
$string['disclosuregroupidentitydesc'] = 'Lire l’identité du site Moodle et les profils nécessaires à l’enregistrement, à l’association des comptes et à la personnalisation.';
$string['disclosuregrouproleassignment'] = 'Écriture d’attribution du rôle formateur';
$string['disclosuregrouproleassignmentdesc'] = 'Attribuer le rôle Manager Raison lorsqu’une invitation autorisée est initiée depuis Raison.';
$string['disclosureheading'] = 'Examiner les informations sur l’intégration Raison';
$string['disclosureintro'] = 'Avant toute activation de service web ou création de jeton, examinez le périmètre d’accès, les fonctions et les finalités ci-dessous.';
$string['disclosuremissing'] = 'Les informations actuelles sur l’intégration doivent être reconnues par l’administrateur qui lance la configuration.';
$string['disclosureopensource'] = 'Le code source du plugin est disponible publiquement pour un audit de sécurité :';
$string['disclosureplanned'] = 'Usage prévu';
$string['disclosureposttrialagreements'] = 'Si votre organisation choisit de continuer à utiliser Raison après la période d’essai gratuite, la poursuite du service nécessitera la conclusion d’accords formels entre Raison et votre organisation. Ces accords définiront les responsabilités de chaque partie et garantiront leur alignement en matière de protection des données, de confidentialité, de sécurité et d’exigences réglementaires applicables.';
$string['disclosureprivacycontact'] = 'Pour toute question sur le traitement externe, la conservation ou la suppression, contactez contact@raison.is.';
$string['disclosurepurpose'] = 'Finalité';
$string['disclosurerole'] = 'Le rôle Manager Raison est distinct du jeton. Il accorde local/corolair:createtutor et local/corolair:viewroles aux utilisateurs Moodle interactifs.';
$string['disclosuresecurityheading'] = 'Propriété du jeton et limite de sécurité';
$string['disclosurestandardised'] = 'La liste des fonctions est standardisée et identique pour toutes les installations prises en charge ; elle n’est pas étendue dynamiquement par client.';
$string['disclosuretokenowner'] = 'Le jeton de service web est créé pour l’administrateur qui lance la configuration. Moodle évalue les appels avec les capacités de cet administrateur.';
$string['disclosuretokentransfer'] = 'Le jeton est transféré à Raison via HTTPS afin que l’intégration appelle les fonctions Moodle autorisées. Il expire après 15 jours et est renouvelé avant son expiration.';
$string['disclosureversion'] = 'Version de l’information';
$string['errortoken'] = 'Erreur lors de la récupération du token';
$string['eventintegrationdisclosureacknowledged'] = 'Informations sur l’intégration reconnues';
$string['eventprivacydeletioncompleted'] = 'Suppression des données privées Raison terminée';
$string['eventremoterequestcompleted'] = 'Requête distante Raison terminée';
$string['eventwebservicetokenlifecycle'] = 'Cycle de vie du jeton de service web Raison mis à jour';
$string['excludedmods'] = 'Activités exclues';
$string['excludedmodsdesc'] = 'Utilisez cette liste pour désactiver les assistants dans certains types d\'activités, par exemple afin d\'empêcher les étudiants de les utiliser pendant une évaluation. Listez les noms courts des activités, séparés par des virgules (ex. : "quiz, assign"). Le nom court correspond au dossier visible dans l\'URL de l\'activité après \'/mod/\' (ex : \'/mod/quiz/\' → \'quiz\'). Cela marche aussi avec des plugins d\'activités externes.';
$string['false'] = 'Chatbot';
$string['frontpagenodetitle'] = 'Raison';
$string['installtroubleshoot'] = 'Si vous rencontrez des problèmes lors de l\'installation, veuillez vous référer au <a href="https://troubleshoot-moodle.raison.is" target="_blank"> guide de dépannage manuel</a>';
$string['invalidredirecturl'] = 'Raison a renvoyé une destination de redirection non fiable.';
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
$string['setupaction'] = 'Ouvrir la configuration Raison';
$string['setupchangeregistration'] = 'Un jeton de service web Moodle sera créé et envoyé à Raison afin d\'enregistrer ce site.';
$string['setupchangerest'] = 'Le protocole REST sera ajouté sans modifier les autres protocoles déjà activés.';
$string['setupchangewebservices'] = 'Les services web Moodle seront activés s\'ils sont actuellement désactivés.';
$string['setupconfirmbutton'] = 'Activer les services web et REST';
$string['setupconfirmquestion'] = 'Acceptez-vous ces modifications globales et le démarrage de l\'enregistrement Raison ?';
$string['setupconsentdescription'] = 'Raison est actuellement inactif. Son activation apporte les modifications globales suivantes :';
$string['setupconsentheading'] = 'Vérifier et approuver l\'activation de Raison';
$string['setupconsentmissing'] = 'L\'enregistrement Raison ne peut pas s\'exécuter sans le consentement enregistré d\'un administrateur.';
$string['setupcontinuebutton'] = 'Démarrer l\'enregistrement Raison';
$string['setupcontinuequestion'] = 'Démarrer l\'enregistrement Raison maintenant ?';
$string['setupcurrentstatus'] = 'État actuel — Services web Moodle : {$a->webservices} ; REST : {$a->rest}.';
$string['setuppagetitle'] = 'Configuration de Raison';
$string['setupqueued'] = 'Votre consentement a été enregistré et l\'enregistrement Raison a été mis en file d\'attente.';
$string['setupqueuedwithoutconsent'] = 'L\'enregistrement Raison a été mis en file d\'attente. Aucun paramètre global de service web n\'a été modifié.';
$string['setupreadydescription'] = 'Les services web Moodle et REST sont déjà activés. Aucun consentement d\'activation n\'est requis et ces paramètres ne seront pas modifiés.';
$string['setupreadyheading'] = 'Les prérequis des services web sont déjà activés';
$string['setupreadynotification'] = 'Raison a été installé. Les services web Moodle et REST sont déjà activés. Un administrateur doit <a href="{$a}">examiner les informations sur l’intégration et démarrer l’enregistrement</a>.';
$string['setuprequirednotification'] = 'Raison est installé mais reste inactif. Un administrateur doit <a href="{$a}">examiner les informations sur l’intégration et approuver les modifications requises des services web</a>.';
$string['setupstatus'] = 'État de l\'intégration';
$string['setupstatuscomplete'] = 'Connecté. Le consentement administrateur est enregistré et l\'enregistrement Raison est terminé.';
$string['setupstatuspending'] = 'Le consentement administrateur est enregistré et l\'enregistrement est en attente. {$a}';
$string['setupstatusready'] = 'Les services web et REST sont déjà activés. Examinez les informations sur l’intégration, puis démarrez l’enregistrement sans consentement d’activation. {$a}';
$string['setupstatusrequired'] = 'La configuration nécessite la reconnaissance des informations sur l’intégration et le consentement administrateur aux modifications des services web. {$a}';
$string['sidepanel'] = 'Positionnement du Tuteur IA à l\'écran';
$string['sidepaneldesc'] = 'Choisissez si vous préférez afficher les Tuteurs IA sur le côté droit des cours sous forme de panneau latéral (recommandé) ou dans le coin inférieur droit comme un chatbot classique.';
$string['taskrotatewebservicetoken'] = 'Renouveler le jeton de service web Moodle de Raison';
$string['tokencreationerror'] = 'Impossible de créer le jeton REST Raison.';
$string['tokenexpirywarningbody'] = 'Raison n\'a pas pu renouveler son jeton de service web Moodle. Le jeton actuel expire le {$a->expiry}. Code d\'erreur sûr : {$a->error}. Vérifiez le cron et la connectivité dans les paramètres Raison.';
$string['tokenexpirywarningsubject'] = 'Le renouvellement du jeton Raison nécessite votre attention';
$string['tokenmissing'] = 'Le jeton de service web Raison actuel est introuvable.';
$string['tokenname'] = 'Jeton REST Raison';
$string['tokenrotationrequestfailed'] = 'La demande de renouvellement du jeton Raison a échoué.';
$string['tokenrotationresponseinvalid'] = 'Raison a renvoyé un accusé de réception de renouvellement invalide.';
$string['tokenrotationretry'] = 'Réessayer le renouvellement du jeton';
$string['tokenrotationretryconfirm'] = 'Mettre en file d\'attente une nouvelle tentative immédiate avec le jeton candidat et l\'identifiant de rotation existants ?';
$string['tokenrotationretryqueued'] = 'La nouvelle tentative de renouvellement du jeton Raison a été mise en file d\'attente.';
$string['tokenrotationstatusfailed'] = 'Le renouvellement du jeton n\'a pas abouti. Le jeton actuel expire le {$a->expiry}. Code d\'erreur sûr : {$a->error}. Moodle réessaiera automatiquement. <a href="{$a->retryurl}">Réessayer maintenant</a>.';
$string['trainerpage'] = 'Raison';
$string['true'] = 'Panneau Latéral';
$string['unexpectederror'] = 'Une erreur inattendue s\'est produite. Veuillez réessayer. Si l\'erreur persiste, veuillez contacter l\'équipe Raison.';
$string['viewrolescapability'] = 'Permet aux utilisateurs de récupérer les rôles Moodle via le service web Raison';
$string['webservicesenableerror'] = 'Impossible d\'activer les services web.';
