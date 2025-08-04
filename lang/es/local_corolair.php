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

$string['pluginname'] = 'Plugin Local de Corolair';
$string['sidepanel'] = 'Posición del Tutor IA en la pantalla';
$string['sidepaneldesc'] = 'Elija si prefiere mostrar los Tutores IA en el lado derecho de los cursos como un Panel lateral (recomendado) o en la esquina inferior derecha como un Chatbot clásico.';
$string['true'] = 'Panel lateral';
$string['false'] = 'Chatbot';
$string['apikey'] = 'Clave API de Corolair';
$string['apikeydesc'] = 'Esta clave se genera durante la instalación del plugin. Guárdela en un lugar seguro. El equipo de soporte de Corolair podría solicitarla.';
$string['corolairlogin'] = 'Cuenta Corolair';
$string['corolairlogindesc'] = 'La cuenta maestra de Corolair está asociada a este correo electrónico. El equipo de soporte de Corolair podría solicitarlo.';
$string['plugininstalledsuccess'] = 'El plugin se ha instalado correctamente. Ahora puede crear y compartir Tutores IA desde la pestaña Corolair. También puede permitir a los profesores/formadores crear Tutores IA asignándoles el rol de "Gestor de Corolair" en Usuarios > Permisos > Asignar roles del sistema. Si encuentra algún problema, no dude en contactar al equipo de Corolair.';
$string['curlerror'] = 'Se ha producido un error al comunicarse con la API de Corolair. No se ha podido registrar su instancia de Moodle, intente nuevamente. Si el problema persiste, póngase en contacto con el equipo de Corolair.';
$string['apikeymissing'] = 'No se ha encontrado la clave API en la respuesta de la API de Corolair.';
$string['corolair:createtutor'] = 'Permite al usuario crear y gestionar tutores dentro del plugin de Corolair.';
$string['noapikey'] = 'No hay clave API de Corolair';
$string['errortoken'] = 'Error al obtener el token';
$string['missingcapability'] = 'No tiene permisos para acceder a esta página';
$string['roleproblem'] = 'Hemos encontrado un problema al crear o asignar el nuevo rol de Gestor de Corolair. Puede configurarlo manualmente permitiendo la capacidad "Plugin Local de Corolair" a cualquier rol del sistema. Si tiene alguna dificultad, póngase en contacto con el equipo de Corolair a través de contact@corolair.com.';
$string['coursenodetitle'] = 'Corolair: Crear Tutor IA';
$string['frontpagenodetitle'] = 'Corolair';
$string['createtutorcapability'] = 'Excluir cursos sin capacidad de edición';
$string['createtutorcapabilitydesc'] = 'El usuario no podrá crear Tutores IA en cursos que no pueda gestionar. Si se establece en "Falso", podrá crearlos en cursos donde solo esté inscrito.';
$string['capabilitytrue'] = 'Verdadero';
$string['capabilityfalse'] = 'Falso';
$string['unexpectederror'] = 'Se ha producido un error inesperado. Intente de nuevo. Si el problema persiste, póngase en contacto con el equipo de Corolair.';
$string['trainerpage'] = 'Corolair';
$string['nocorolairlogin'] = 'No hay ninguna cuenta asociada';
$string['createtutorcapability'] = 'Permite a los usuarios crear y gestionar Tutores IA dentro de Corolair';
$string['tokenname'] = 'Token REST de Corolair';
$string['rolename'] = 'Gestor de Corolair';
$string['roledescription'] = 'Rol para la gestión de Tutores IA en Corolair';
$string['privacy:metadata:corolair'] = 'Los metadatos enviados a Corolair permiten acceder a sus datos de forma fluida en el sistema remoto.';
$string['privacy:metadata:corolair:userid'] = 'El identificador del usuario se envía para reconocerle de manera única en Corolair.';
$string['privacy:metadata:corolair:useremail'] = 'Su dirección de correo electrónico se envía para identificarle de forma única en Corolair y facilitar la comunicación.';
$string['privacy:metadata:corolair:userfirstname'] = 'Su nombre se envía para personalizar su experiencia en Corolair y facilitar su identificación en sus conversaciones con el Tutor.';
$string['privacy:metadata:corolair:userlastname'] = 'Su apellido se envía para personalizar su experiencia en Corolair y facilitar su identificación en sus conversaciones con el Tutor.';
$string['privacy:metadata:corolair:userrolename'] = 'Su rol se envía para gestionar sus permisos en Corolair.';
$string['privacy:metadata:corolair:interaction'] = 'Los registros de sus interacciones, como tutores creados y conversaciones, se envían para mejorar su experiencia.';
$string['localhosterror'] = 'No es posible registrar la instancia de Moodle en Corolair porque el sitio se está ejecutando en localhost.';
$string['webservicesenableerror'] = 'No se han podido activar los servicios web.';
$string['restprotocolenableerror'] = 'No se ha podido activar el protocolo REST.';
$string['servicecreationerror'] = 'No se ha podido crear el servicio REST de Corolair.';
$string['capabilityassignerror'] = 'No se ha podido asignar la capacidad "{$a}" al rol.';
$string['tokencreationerror'] = 'No se ha podido generar el token REST de Corolair.';
$string['installtroubleshoot'] = 'Si encuentra algún problema durante la instalación, consulte la <a href="https://corolair.notion.site/Moodle-Integration-EN-5d5dc1e61f8d4bd89372a6b8009ec4e4?pvs=4" target="_blank">guía de solución de problemas</a>.';
$string['adhocqueued'] = 'La sincronización con los servicios de Corolair debería haber comenzado en su tarea ad-hoc <a href="{$a->adhoc_link}">\local_corolair\task\setup_corolair_connection_task</a>. Si no es así, genere una clave API desde <a href="{$a->trainer_page_link}">aquí</a>.';
$string['corolairtuto'] = 'Aprenda a utilizar Corolair consultando <a href="https://corolair.notion.site/Moodle-Integration-EN-5d5dc1e61f8d4bd89372a6b8009ec4e4?pvs=4" target="_blank">este tutorial</a>.';
$string['customcss'] = 'CSS personalizado';
$string['advancedsettings'] = 'Configuraciones avanzadas';
$string['advancedsettingsdescription'] = 'Estas son las configuraciones avanzadas del plugin de Corolair. Si necesita ayuda, no dude en contactar al equipo de Corolair, estaremos encantados de asistirle.';
$string['customcss_desc'] = 'El tema o la configuración de su Moodle podrían afectar la visualización de la <a href="{$a->trainer_page_link}">página del Tutor</a>, causando posibles problemas de diseño. Si nota irregularidades en la presentación, puede introducir aquí su propio CSS para ajustar el diseño. <strong>Utilice esta opción solo si es necesario y si tiene conocimientos de CSS.</strong> Haga clic <a href="{$a->reset_css_link}">aquí</a> para restablecer los estilos predeterminados.';
$string['reset_success'] = 'Restablecimiento exitoso';
$string['enablecustomcss'] = 'Activar CSS personalizado';
$string['enablecustomcss_desc'] = 'Marque esta casilla para permitir modificaciones con CSS personalizado. Se recomienda solo si necesita corregir problemas de visualización causados por su tema o configuración de Moodle.';
$string['quiztrainerpage'] = 'Corolair Quiz - Entrenador';
$string['quizstudentpage'] = 'Corolair Quiz - Estudiante';
