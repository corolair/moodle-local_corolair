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

$string['adhocqueued'] = 'La sincronización con los servicios de Raison debería haber comenzado en su tarea ad-hoc <a href="{$a->adhoc_link}">\local_corolair\task\setup_corolair_connection_task</a>. Si no es así, genere una clave API desde <a href="{$a->trainer_page_link}">aquí</a>.';
$string['apikey'] = 'Clave API de Raison';
$string['apikeydesc'] = 'Esta clave se genera durante la instalación del plugin. Guárdela en un lugar seguro. El equipo de soporte de Raison podría solicitarla.';
$string['apikeymissing'] = 'No se ha encontrado la clave API en la respuesta de la API de Raison.';
$string['apikeyrotate'] = 'Renovar clave API';
$string['apikeyrotateconfirm'] = 'Esto genera una nueva clave API de Raison e invalida inmediatamente la actual. ¿Desea continuar?';
$string['apikeyrotatefailed'] = 'La renovación de la clave API de Raison falló. Verifique los servicios web y la conectividad, y vuelva a intentarlo.';
$string['apikeyrotatenotoken'] = 'No se encontró ningún token de servicio web de Raison. Complete la configuración del plugin antes de renovar la clave API.';
$string['apikeyrotatesuccess'] = 'Se generó una nueva clave API de Raison. La clave anterior ya no es válida.';
$string['apikeyset'] = 'La clave API se configuró correctamente.';
$string['assignmanagerrolecapability'] = 'Asignar el rol de gestor de Raison mediante la API de integración de alcance limitado.';
$string['calendlydemo'] = 'Para poder ayudarte de manera efectiva, primero cuéntanos tu caso de uso y tus necesidades en una llamada de descubrimiento con el equipo de Raison. Una vez entendamos tus requerimientos, nuestros desarrolladores se encargarán de solucionar los problemas de conexión con tu instancia de Moodle. Reserva tu llamada  <strong> <a href="https://discoverycall.raison.is/" target="_blank">aquí</a> </strong>.';
$string['capabilityassignerror'] = 'No se ha podido asignar la capacidad "{$a}" al rol.';
$string['capabilityfalse'] = 'Falso';
$string['capabilitytrue'] = 'Verdadero';
$string['corolair:assignmanagerrole'] = 'Asignar el rol de gestor de Raison mediante la API de integración de alcance limitado.';
$string['corolair:createtutor'] = 'Permite al usuario crear y gestionar tutores dentro del plugin de Raison.';
$string['corolair:viewroles'] = 'Permite al usuario recuperar los metadatos de los roles de Moodle para la integración con Raison.';
$string['coursenodetitle'] = 'Asistente de IA de Raison';
$string['createtutorcapability'] = 'Permite a los usuarios crear y gestionar Tutores IA dentro de Raison';
$string['createtutorcapabilitydesc'] = 'El usuario no podrá crear Tutores IA en cursos que no pueda gestionar. Si se establece en "Falso", podrá crearlos en cursos donde solo esté inscrito.';
$string['curlerror'] = 'Se ha producido un error al comunicarse con la API de Raison. No se ha podido registrar su instancia de Moodle, intente nuevamente. Si el problema persiste, póngase en contacto con el equipo de Raison.';
$string['deregisterfailed'] = 'Raison no pudo confirmar la baja remota tras los intentos disponibles. Se ha revocado el acceso a Moodle y se han eliminado las credenciales locales de la integración, pero no se ha confirmado técnicamente la eliminación de los datos que Raison ya conserva. Contacte con contact@raison.is conforme al contrato de servicio o acuerdo de tratamiento de datos aplicable e indique la URL de este sitio Moodle para completar el proceso de eliminación.';
$string['disabletokenrotation'] = 'Desactivar la rotación del token de servicio web';
$string['disabletokenrotationdesc'] = 'De forma predeterminada, el token de servicio web de Raison caduca a los 15 días y se reemplaza automáticamente antes de su vencimiento. Active esta opción únicamente si no se puede confiar en ese reemplazo automático, por ejemplo cuando el cron de Moodle no se ejecuta con regularidad o cuando no está garantizado el acceso saliente a Raison. Tres consecuencias: el token deja de caducar; cada vez que cambie esta configuración, el token anterior seguirá siendo válido hasta 7 días más para no interrumpir las peticiones en curso; y Raison deja de verificar periódicamente que este sitio siga concediendo las funciones que necesita la integración, por lo que una configuración incorrecta se detectará más tarde. Aplicar un cambio requiere el cron y una llamada correcta a Raison; el progreso se indica más arriba.';
$string['disclosureaccess'] = 'Acceso';
$string['disclosureaccessread'] = 'Lectura';
$string['disclosureaccesswrite'] = 'Escritura';
$string['disclosureacknowledgebutton'] = 'Confirmo que he revisado esta información';
$string['disclosureacknowledgmentnote'] = 'Esta confirmación acredita que revisó el alcance de la integración. No se presenta como consentimiento legal.';
$string['disclosureallowlist'] = 'El token está limitado a la lista fija de funciones del servicio corolair_rest. No puede invocar funciones de servicios web de Moodle fuera de esa lista.';
$string['disclosurecapabilitiesheading'] = 'Por qué intervienen capacidades de Moodle';
$string['disclosurecapabilitiesintro'] = 'Moodle evalúa las capacidades del propietario del token además de la lista del servicio. El token pertenece a una cuenta de servicio dedicada que no es administradora del sitio y que posee exactamente las capacidades indicadas abajo y ninguna más. Cada capacidad indicada la exige una función concreta de la lista anterior.';
$string['disclosurecapability'] = 'Capacidad';
$string['disclosurecapcompletion'] = 'Permite lecturas de finalización reservadas para futuras tutorías y análisis adaptativos. Estos datos no se procesan actualmente.';
$string['disclosurecapcontent'] = 'Permite leer el contenido de los tipos de actividad admitidos y descargar los archivos correspondientes.';
$string['disclosurecapcoursevisibility'] = 'Permite leer la estructura de cursos y categorías, incluidos elementos ocultos, como fuente y contexto organizativo de los tutores. La cuenta de servicio no está matriculada en ningún curso: es course:view lo que le da acceso de lectura a los cursos. También lee actividades y secciones sujetas a restricciones de acceso, incluidas las configuradas para ocultarse por completo: Moodle las omite por entero de su respuesta, y una sincronización de contenido no puede distinguir una actividad omitida de una eliminada.';
$string['disclosurecapexam'] = 'Permite que el flujo específico de colocación de exámenes cree, renombre y elimine la actividad Herramienta externa que contiene un examen de Raison. Es la única parte de la integración que escribe en un curso, y solo puede alcanzar una actividad LTI: la función de eliminación resuelve su objetivo mediante la tabla LTI, por lo que no puede eliminar ningún otro tipo de actividad.';
$string['disclosurecapidentity'] = 'Permite leer identidad y correo para vincular cuentas, invitar, personalizar y limitar accesos. Son filtros de lectura: no permiten modificar ninguna cuenta de usuario. Moodle clasifica moodle/course:useremail como escritura y la denomina «Activar/desactivar dirección de correo», lo que la exagera: no lleva ningún indicador de riesgo y solo permite leer una dirección que la persona ha decidido no mostrar. Se incluye porque, de lo contrario, Moodle omite la dirección por completo en sitios que han restringido sus campos de identidad, y porque Raison vincula las cuentas por correo cuando no tiene guardado un identificador de Moodle.';
$string['disclosurecapparticipants'] = 'Permite leer participantes y contenido de todos los grupos para delimitar correctamente el acceso.';
$string['disclosurecapprotocol'] = 'Permite que el token llame al servicio web REST de Moodle. Sin ella, cualquier petición se rechaza antes de considerar la función o las demás capacidades.';
$string['disclosurecaproleassign'] = 'Permite leer los roles de Moodle y asignar el rol Gestor de Raison a un formador invitado desde Raison. Solo puede asignar ese rol, que no concede nada fuera de este plugin.';
$string['disclosuredataaccess'] = 'Las matrículas, roles, grupos y relaciones de participantes determinan quién puede acceder a cada tutor.';
$string['disclosuredatacompletion'] = 'La finalización de actividades y cursos se reserva para una función adaptativa futura y no se procesa actualmente.';
$string['disclosuredatacourse'] = 'Las categorías, estructuras, secciones, recursos, lecciones, SCORM y metadatos LTI sirven como fuentes y contexto de los tutores.';
$string['disclosuredataexam'] = 'Cuando se usa la colocación de exámenes, la información LTI permite crear, modificar o eliminar la colocación solicitada.';
$string['disclosuredataheading'] = 'Uso de datos personales y educativos';
$string['disclosuredataidentity'] = 'Los identificadores, nombres, correos y roles permiten aprovisionar cuentas, enviar invitaciones, vincular identidades, personalizar y controlar sesiones.';
$string['disclosuredatasite'] = 'La URL, el nombre del sitio Moodle y la identidad del servicio registran e identifican esta instalación.';
$string['disclosurefiletransfer'] = 'La descarga de archivos está habilitada en este servicio para poder leer los recursos de curso admitidos. La carga está deshabilitada: la integración nunca escribe archivos en Moodle, y Moodle controla las cargas únicamente mediante ese ajuste del servicio, sin tener en cuenta la lista de funciones.';
$string['disclosurefunction'] = 'Función de servicio web';
$string['disclosurefunctionsheading'] = 'Lista fija de funciones de servicio web';
$string['disclosurefunctionsintro'] = 'El plugin estandarizado expone las siguientes 26 funciones. Expanda cada grupo para revisar los nombres exactos y su clasificación.';
$string['disclosuregroupcompletion'] = 'Lecturas de finalización';
$string['disclosuregroupcompletiondesc'] = 'Leer la finalización de actividades y cursos para futuras tutorías adaptativas. Estos datos no se procesan actualmente.';
$string['disclosuregroupcontent'] = 'Lecturas de cursos y contenido educativo';
$string['disclosuregroupcontentdesc'] = 'Leer cursos, secciones, recursos, lecciones, paquetes SCORM y metadatos LTI usados para crear y organizar tutores.';
$string['disclosuregroupenrolment'] = 'Lecturas de matrículas, participantes y roles';
$string['disclosuregroupenrolmentdesc'] = 'Leer miembros, participantes, capacidades y roles de Moodle para delimitar el acceso a tutores.';
$string['disclosuregroupexamplacement'] = 'Escrituras de colocación de exámenes y actividades';
$string['disclosuregroupexamplacementdesc'] = 'Escrituras específicas usadas solo cuando un administrador inicia el flujo de colocación de exámenes.';
$string['disclosuregroupidentity'] = 'Lecturas del sitio e identidad';
$string['disclosuregroupidentitydesc'] = 'Leer la identidad del sitio Moodle y perfiles necesarios para registro, vinculación de cuentas y personalización.';
$string['disclosuregrouproleassignment'] = 'Escritura de asignación del rol de formador';
$string['disclosuregrouproleassignmentdesc'] = 'Asignar el rol Gestor de Raison cuando se inicia una invitación autorizada desde Raison.';
$string['disclosureheading'] = 'Revise la información de la integración Raison';
$string['disclosureintro'] = 'Antes de habilitar servicios web o crear un token, revise el límite de acceso, las funciones y las finalidades siguientes.';
$string['disclosuremissing'] = 'El administrador que inicia la configuración debe confirmar la versión actual de la información de integración.';
$string['disclosureopensource'] = 'El código fuente del plugin está disponible públicamente para revisión de seguridad:';
$string['disclosureplanned'] = 'Uso previsto';
$string['disclosureposttrialagreements'] = 'Si su organización decide continuar utilizando Raison después del periodo de prueba gratuito, la continuidad del servicio requerirá la formalización de acuerdos entre Raison y su organización. Estos acuerdos definirán las responsabilidades de cada parte y garantizarán su alineación en materia de protección de datos, privacidad, seguridad y requisitos normativos aplicables.';
$string['disclosureprivacycontact'] = 'Para consultas sobre tratamiento externo, conservación o eliminación, contacte con contact@raison.is.';
$string['disclosurepurpose'] = 'Finalidad';
$string['disclosurerole'] = 'El rol Gestor de Raison es independiente del token. Concede local/corolair:createtutor y local/corolair:viewroles a usuarios interactivos de Moodle.';
$string['disclosuresecurityheading'] = 'Propiedad del token y límite de seguridad';
$string['disclosureserviceaccountbody'] = 'El token pertenece a una cuenta de Moodle dedicada llamada «{$a}» que este plugin crea y mantiene. No es administradora del sitio, no tiene contraseña y no puede iniciar sesión; solo posee las capacidades indicadas abajo. Desinstalar el plugin suspende la cuenta y elimina su rol; la cuenta se conserva, sin ningún dato personal, para que reinstalar no acumule duplicados.';
$string['disclosureserviceaccountcontact'] = 'La cuenta figura con la dirección {$a} para que cualquiera que la encuentre entre sus usuarios sepa de quién es y dónde preguntar por ella. Moodle nunca le envía correo: la cuenta se crea con las notificaciones desactivadas y su dirección oculta al resto de usuarios. Si esa dirección ya pertenece a una cuenta real de este sitio, la cuenta de servicio recibe una no enrutable para que la cuenta real no se vea afectada.';
$string['disclosureserviceaccountheading'] = 'La cuenta de servicio dedicada';
$string['disclosurestandardised'] = 'La lista de funciones es estándar e idéntica en todas las instalaciones compatibles; no se amplía dinámicamente por cliente.';
$string['disclosuretokenowner'] = 'El token de servicio web pertenece a una cuenta de servicio dedicada que no es administradora, y el servicio está restringido de modo que ninguna otra cuenta puede usarlo. Moodle evalúa cada llamada con las capacidades de esa cuenta, indicadas abajo.';
$string['disclosuretokenrotationdisabled'] = 'Este sitio ha desactivado la rotación del token. El token no caduca y Raison ya no verifica periódicamente que este sitio siga concediendo las funciones enumeradas a continuación.';
$string['disclosuretokentransfer'] = 'El token se transfiere a Raison mediante HTTPS para que la integración invoque las funciones permitidas de Moodle. De forma predeterminada caduca a los 15 días y se rota antes de su vencimiento; un administrador del sitio puede desactivar la rotación, en cuyo caso no caduca.';
$string['disclosureuninstall'] = 'Al desinstalar, el plugin intenta hasta tres veces la baja remota antes de revocar el acceso al servicio web de Moodle y eliminar las credenciales locales de la integración. Si no se puede confirmar la eliminación remota, los datos transferidos anteriormente siguen sujetos al contrato de servicio o acuerdo de tratamiento de datos aplicable, y el administrador debe contactar con contact@raison.is indicando la URL del sitio Moodle para completar el proceso de eliminación.';
$string['disclosureversion'] = 'Versión de la información';
$string['errortoken'] = 'Error al obtener el token';
$string['eventintegrationdisclosureacknowledged'] = 'Información de integración confirmada';
$string['eventprivacydeletioncompleted'] = 'Eliminación de datos privados de Raison completada';
$string['eventremoterequestcompleted'] = 'Solicitud remota de Raison completada';
$string['eventwebservicetokenlifecycle'] = 'Ciclo de vida del token de servicio web de Raison actualizado';
$string['excludedmods'] = 'Actividades excluidas';
$string['excludedmodsdesc'] = 'Use esta lista para desactivar los asistentes en tipos específicos de actividades, por ejemplo para evitar que los estudiantes los utilicen durante evaluaciones. Proporcione una lista separada por comas con los nombres cortos de los módulos de actividad (ej.: "quiz, assign"). El nombre corto es la carpeta que aparece en la URL de la actividad después de /mod/ (ej.: /mod/quiz/ → quiz). Esto también funciona con módulos de actividad proporcionados por plugins externos.';
$string['false'] = 'Chatbot';
$string['frontpagenodetitle'] = 'Raison';
$string['installfailed'] = 'El plugin Raison no pudo completar su instalación: {$a}';
$string['installtroubleshoot'] = 'Si encuentra algún problema durante la instalación, consulte la <a href="https://troubleshoot-moodle.raison.is" target="_blank">guía de solución de problemas</a>.';
$string['invalidredirecturl'] = 'Raison devolvió un destino de redirección no confiable.';
$string['legacycredentialmigrationblockednotice'] = 'Las credenciales heredadas de Raison aún no se han reemplazado porque actualmente ningún administrador del sitio puede ser propietario de la integración. Asigne la capacidad «moodle/site:config» a un administrador activo; Raison lo reintenta automáticamente cada hora.';
$string['legacycredentialmigrationdeferred'] = 'El reemplazo de las credenciales heredadas de Raison no pudo iniciarse durante la actualización. La actualización finalizó y Raison lo reintentará automáticamente cada hora. Consulte Administración del sitio > Plugins > Plugins locales > Raison para ver el estado actual.';
$string['legacycredentialmigrationfailed'] = 'Las credenciales heredadas de Raison no pudieron reemplazarse de forma verificable. Moodle volverá a intentar la migración automáticamente mediante su ejecutor de tareas ad hoc.';
$string['legacycredentialmigrationpendingnotice'] = 'Se están reemplazando las credenciales heredadas de una versión anterior del plugin. Hasta que Raison confirme el reemplazo, el token de servicio web heredado sigue en uso y dejará de funcionar dentro de unas horas, se haya completado o no el reemplazo. Moodle lo reintenta automáticamente; si este aviso persiste, consulte Administración del sitio > Servidor > Tareas > Tareas ad hoc para ver si hay una tarea de migración de Raison fallando.';
$string['localhosterror'] = 'No es posible registrar la instancia de Moodle en Raison porque el sitio se está ejecutando en localhost.';
$string['missingcapability'] = 'No tiene permisos para acceder a esta página';
$string['noapikey'] = 'No hay clave API de Raison';
$string['pluginname'] = 'Plugin Local de Raison';
$string['privacy:metadata:config_plugins'] = 'El plugin almacena, en su configuración local, un registro del administrador que consintió la integración y reconoció la divulgación.';
$string['privacy:metadata:config_plugins:serviceaccountid'] = 'El ID de la cuenta de servicio dedicada que posee el token de servicio web de Raison. El plugin crea esta cuenta y no representa a ninguna persona.';
$string['privacy:metadata:config_plugins:setupconsentedat'] = 'El momento en que el administrador consintió habilitar la integración de Raison.';
$string['privacy:metadata:config_plugins:setupconsentedby'] = 'El ID del administrador que consintió habilitar la integración de Raison.';
$string['privacy:metadata:config_plugins:setupdisclosureacknowledgedat'] = 'El momento en que el administrador reconoció la divulgación de la integración.';
$string['privacy:metadata:config_plugins:setupdisclosureacknowledgedby'] = 'El ID del administrador que reconoció la divulgación de la integración.';
$string['privacy:metadata:config_plugins:webservicetokenownerid'] = 'El ID del usuario que posee el token de servicio web de Raison actual. Es la cuenta de servicio dedicada, salvo durante la transferencia desde una versión anterior, cuando brevemente es el administrador que configuró la integración.';
$string['privacy:metadata:raison'] = 'Los metadatos enviados a Raison permiten acceder a sus datos de forma fluida en el sistema remoto.';
$string['privacy:metadata:raison:interaction'] = 'Los registros de sus interacciones, como tutores creados y conversaciones, se envían para mejorar su experiencia.';
$string['privacy:metadata:raison:useremail'] = 'Su dirección de correo electrónico se envía para identificarle de forma única en Raison y facilitar la comunicación.';
$string['privacy:metadata:raison:userfirstname'] = 'Su nombre se envía para personalizar su experiencia en Raison y facilitar su identificación en sus conversaciones con el Tutor.';
$string['privacy:metadata:raison:userid'] = 'El identificador del usuario se envía para reconocerle de manera única en Raison.';
$string['privacy:metadata:raison:userlastname'] = 'Su apellido se envía para personalizar su experiencia en Raison y facilitar su identificación en sus conversaciones con el Tutor.';
$string['privacy:metadata:raison:userrolename'] = 'Su rol se envía para gestionar sus permisos en Raison.';
$string['privacy:setupsubcontext'] = 'Configuración de la integración de Raison';
$string['raisontuto'] = 'Aprenda a utilizar Raison consultando <a href="https://troubleshoot-moodle.raison.is" target="_blank">este tutorial</a>.';
$string['redirectingmessage'] = 'Si no se redirige automáticamente, haga clic en el botón a continuación para continuar a Raison.';
$string['reloadpage'] = 'Recargar la página';
$string['restprotocolenableerror'] = 'No se ha podido activar el protocolo REST.';
$string['retryregistration'] = 'Reintentar el registro de Raison';
$string['retryseparator'] = 'o';
$string['roledescription'] = 'Rol para la gestión de Tutores IA en Raison';
$string['rolename'] = 'Gestor de Raison';
$string['roleproblem'] = 'Hemos encontrado un problema al crear o asignar el nuevo rol de Gestor de Raison. Puede configurarlo manualmente permitiendo la capacidad "Plugin Local de Raison" a cualquier rol del sistema. Si tiene alguna dificultad, póngase en contacto con el equipo de Raison a través de contact@raison.is.';
$string['serviceaccountdescription'] = 'Cuenta dedicada no administradora que usa la integración de Raison para llamar al servicio web de Moodle. No puede iniciar sesión y solo posee las capacidades que la integración necesita. Creada y mantenida automáticamente por el plugin Raison.';
$string['serviceaccountfirstname'] = 'Raison';
$string['serviceaccountlastname'] = 'Servicio de integración';
$string['serviceaccountmigrationpendingnotice'] = 'El token de servicio web de Raison se está transfiriendo desde el administrador que configuró la integración a una cuenta de servicio dedicada no administradora. Ambos siguen siendo válidos hasta que la transferencia termine, así que nada deja de funcionar entretanto. Moodle la completa en su próxima ejecución programada y después revoca el token del administrador.';
$string['serviceaccountremovalfailed'] = 'No se pudo suspender la cuenta de servicio de la integración de Raison durante la desinstalación. Puede suspender o eliminar manualmente la cuenta «{$a->username}» en Administración del sitio > Usuarios > Cuentas. Detalles: {$a->error}';
$string['serviceaccountroledescription'] = 'Capacidades que posee la cuenta de servicio de la integración de Raison. Este rol se mantiene automáticamente y no está pensado para usuarios interactivos.';
$string['serviceaccountrolename'] = 'Servicio de integración de Raison';
$string['servicecreationerror'] = 'No se ha podido crear el servicio REST de Raison.';
$string['setupaction'] = 'Abrir la configuración de Raison';
$string['setupchangeregistration'] = 'Se creará un token de servicio web de Moodle y se enviará a Raison para registrar este sitio.';
$string['setupchangerest'] = 'Se añadirá el protocolo REST, conservando todos los protocolos que ya estén habilitados.';
$string['setupchangetokenlifetime'] = 'De forma predeterminada, el token caducará a los 15 días y se reemplazará automáticamente antes de su vencimiento.';
$string['setupchangewebservices'] = 'Los servicios web de Moodle se habilitarán si actualmente están deshabilitados.';
$string['setupconfirmbutton'] = 'Habilitar servicios web y REST';
$string['setupconfirmquestion'] = '¿Autoriza estos cambios globales y el inicio del registro de Raison?';
$string['setupconsentdescription'] = 'Raison está inactivo actualmente. Al activarlo se realizarán los siguientes cambios globales:';
$string['setupconsentheading'] = 'Revisar y aprobar la activación de Raison';
$string['setupconsentmissing'] = 'El registro de Raison no puede ejecutarse sin el consentimiento registrado de un administrador.';
$string['setupcontinuebutton'] = 'Iniciar el registro de Raison';
$string['setupcontinuequestion'] = '¿Iniciar ahora el registro de Raison?';
$string['setupcurrentstatus'] = 'Estado actual — Servicios web de Moodle: {$a->webservices}; REST: {$a->rest}.';
$string['setupdisablerotation'] = 'No rotar el token de servicio web';
$string['setupdisablerotationdesc'] = 'Deje esta casilla sin marcar salvo que aquí no se pueda confiar en el reemplazo automático, por ejemplo cuando el cron de Moodle no se ejecuta con regularidad o cuando no está garantizado el acceso saliente a Raison. Si la marca, el token creado para este sitio no caducará ni se reemplazará, y Raison dejará de verificar periódicamente que este sitio siga concediendo las funciones que necesita la integración. Podrá cambiar esta opción más adelante en la configuración del plugin.';
$string['setupdisablerotationforcedoff'] = 'La rotación del token la impone la configuración de este servidor y no se puede desactivar aquí. El token creado para este sitio caducará a los 15 días y se reemplazará automáticamente.';
$string['setupdisablerotationforcedon'] = 'La rotación del token está desactivada por la configuración de este servidor y no se puede activar aquí. El token creado para este sitio no caducará.';
$string['setuppagetitle'] = 'Configuración de Raison';
$string['setupqueued'] = 'Su consentimiento se registró y el registro de Raison se añadió a la cola.';
$string['setupqueuedwithoutconsent'] = 'El registro de Raison se añadió a la cola. No se modificó ninguna configuración global de servicios web.';
$string['setupreadydescription'] = 'Los servicios web de Moodle y REST ya están habilitados, por lo que no se requiere consentimiento para habilitarlos y no se modificarán estos ajustes.';
$string['setupreadyheading'] = 'Los requisitos de servicios web ya están habilitados';
$string['setupreadynotification'] = 'Raison se instaló. Los servicios web de Moodle y REST ya están habilitados. Un administrador debe <a href="{$a}">revisar la información de integración e iniciar el registro</a>.';
$string['setuprequirednotification'] = 'Raison está instalado, pero permanece inactivo. Un administrador debe <a href="{$a}">revisar la información de integración y aprobar los cambios necesarios en los servicios web</a>.';
$string['setupstatus'] = 'Estado de la integración';
$string['setupstatuscomplete'] = 'Conectado. El consentimiento del administrador está registrado y el registro de Raison ha finalizado.';
$string['setupstatuspending'] = 'El consentimiento del administrador está registrado y el registro está pendiente. {$a}';
$string['setupstatusready'] = 'Los servicios web y REST ya están habilitados. Revise la información de integración e inicie el registro sin consentimiento de habilitación. {$a}';
$string['setupstatusrequired'] = 'La configuración requiere confirmar la información de integración y el consentimiento del administrador para los cambios de servicios web. {$a}';
$string['sidepanel'] = 'Posición del Tutor IA en la pantalla';
$string['sidepaneldesc'] = 'Elija si prefiere mostrar los Tutores IA en el lado derecho de los cursos como un Panel lateral (recomendado) o en la esquina inferior derecha como un Chatbot clásico.';
$string['taskrotatewebservicetoken'] = 'Rotar el token de servicio web de Moodle de Raison';
$string['tokencreationerror'] = 'No se ha podido generar el token REST de Raison.';
$string['tokenexpiryunknown'] = 'una fecha desconocida';
$string['tokenexpirywarningbody'] = 'Raison no pudo rotar su token de servicio web de Moodle. El token actual caduca el {$a->expiry}. Código de error seguro: {$a->error}. Revise el cron y la conectividad en la configuración de Raison.';
$string['tokenexpirywarningbodylifetime'] = 'Raison no pudo aplicar la configuración de rotación del token, por lo que el token de servicio web de Moodle conserva su vigencia anterior. Código de error seguro: {$a->error}. Revise el cron y la conectividad en la configuración de Raison.';
$string['tokenexpirywarningsubject'] = 'La rotación del token de Raison requiere atención';
$string['tokenmissing'] = 'No se encontró el token de servicio web actual de Raison.';
$string['tokenname'] = 'Token REST de Raison';
$string['tokenrotationdisabled'] = 'La rotación del token está desactivada. El token de servicio web de Raison no caduca ni se reemplaza automáticamente.';
$string['tokenrotationdisabledpending'] = 'La rotación del token está desactivada, pero el token actual todavía conserva su fecha de caducidad original. Moodle aplicará el cambio en la próxima ejecución programada, lo que requiere el cron y una llamada correcta a Raison.';
$string['tokenrotationrequestfailed'] = 'La solicitud de rotación del token de Raison ha fallado.';
$string['tokenrotationresponseinvalid'] = 'Raison devolvió una confirmación de rotación de token no válida.';
$string['tokenrotationretry'] = 'Reintentar la rotación del token';
$string['tokenrotationretryconfirm'] = '¿Añadir a la cola un reintento inmediato usando el token candidato y el identificador de rotación existentes?';
$string['tokenrotationretryqueued'] = 'El reintento de rotación del token de Raison se añadió a la cola.';
$string['tokenrotationstatusfailed'] = 'La rotación del token no ha finalizado. El token actual caduca el {$a->expiry}. Código de error seguro: {$a->error}. Moodle volverá a intentarlo automáticamente. <a href="{$a->retryurl}">Reintentar ahora</a>.';
$string['tokenrotationstatusfailedlifetime'] = 'La configuración de rotación del token todavía no se ha aplicado, por lo que el token actual conserva su vigencia anterior. Código de error seguro: {$a->error}. Normalmente esto significa que Moodle no pudo comunicarse con Raison, o que Raison aún no se ha actualizado para aceptar tokens sin caducidad. Moodle volverá a intentarlo automáticamente. <a href="{$a->retryurl}">Aplicar ahora</a>.';
$string['trainerpage'] = 'Raison';
$string['true'] = 'Panel lateral';
$string['unexpectederror'] = 'Se ha producido un error inesperado. Intente de nuevo. Si el problema persiste, póngase en contacto con el equipo de Raison.';
$string['uninstallroleremovalfailed'] = 'El rol Gestor de Raison no se pudo eliminar durante la desinstalación y puede seguir existiendo en Administración del sitio > Usuarios > Permisos > Definir roles. Puede eliminarlo manualmente. Detalles: {$a}';
$string['uninstallserviceroleremovalfailed'] = 'El rol Servicio de integración de Raison no se pudo eliminar durante la desinstalación. Sigue teniendo capacidades a nivel de sistema, así que elimínelo manualmente en Administración del sitio > Usuarios > Permisos > Definir roles. Detalles: {$a}';
$string['viewrolescapability'] = 'Permite a los usuarios recuperar los roles de Moodle a través del servicio web de Raison';
$string['webservicesenableerror'] = 'No se han podido activar los servicios web.';
