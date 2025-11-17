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

$string['pluginname'] = 'Plugin Local de Raison';
$string['sidepanel'] = 'Posición del Tutor IA en la pantalla';
$string['sidepaneldesc'] = 'Elija si prefiere mostrar los Tutores IA en el lado derecho de los cursos como un Panel lateral (recomendado) o en la esquina inferior derecha como un Chatbot clásico.';
$string['true'] = 'Panel lateral';
$string['false'] = 'Chatbot';
$string['apikey'] = 'Clave API de Raison';
$string['apikeydesc'] = 'Esta clave se genera durante la instalación del plugin. Guárdela en un lugar seguro. El equipo de soporte de Raison podría solicitarla.';
$string['raisonlogin'] = 'Cuenta Raison';
$string['raisonlogindesc'] = 'La cuenta maestra de Raison está asociada a este correo electrónico. El equipo de soporte de Raison podría solicitarlo.';
$string['curlerror'] = 'Se ha producido un error al comunicarse con la API de Raison. No se ha podido registrar su instancia de Moodle, intente nuevamente. Si el problema persiste, póngase en contacto con el equipo de Raison.';
$string['apikeymissing'] = 'No se ha encontrado la clave API en la respuesta de la API de Raison.';
$string['corolair:createtutor'] = 'Permite al usuario crear y gestionar tutores dentro del plugin de Raison.';
$string['noapikey'] = 'No hay clave API de Raison';
$string['errortoken'] = 'Error al obtener el token';
$string['missingcapability'] = 'No tiene permisos para acceder a esta página';
$string['roleproblem'] = 'Hemos encontrado un problema al crear o asignar el nuevo rol de Gestor de Raison. Puede configurarlo manualmente permitiendo la capacidad "Plugin Local de Raison" a cualquier rol del sistema. Si tiene alguna dificultad, póngase en contacto con el equipo de Raison a través de contact@raison.is.';
$string['coursenodetitle'] = 'Asistente de IA de Raison';
$string['frontpagenodetitle'] = 'Raison';
$string['createtutorcapabilitydesc'] = 'El usuario no podrá crear Tutores IA en cursos que no pueda gestionar. Si se establece en "Falso", podrá crearlos en cursos donde solo esté inscrito.';
$string['capabilitytrue'] = 'Verdadero';
$string['capabilityfalse'] = 'Falso';
$string['unexpectederror'] = 'Se ha producido un error inesperado. Intente de nuevo. Si el problema persiste, póngase en contacto con el equipo de Raison.';
$string['trainerpage'] = 'Raison';
$string['noraisonlogin'] = 'No hay ninguna cuenta asociada';
$string['createtutorcapability'] = 'Permite a los usuarios crear y gestionar Tutores IA dentro de Raison';
$string['tokenname'] = 'Token REST de Raison';
$string['rolename'] = 'Gestor de Raison';
$string['roledescription'] = 'Rol para la gestión de Tutores IA en Raison';
$string['privacy:metadata:raison'] = 'Los metadatos enviados a Raison permiten acceder a sus datos de forma fluida en el sistema remoto.';
$string['privacy:metadata:raison:userid'] = 'El identificador del usuario se envía para reconocerle de manera única en Raison.';
$string['privacy:metadata:raison:useremail'] = 'Su dirección de correo electrónico se envía para identificarle de forma única en Raison y facilitar la comunicación.';
$string['privacy:metadata:raison:userfirstname'] = 'Su nombre se envía para personalizar su experiencia en Raison y facilitar su identificación en sus conversaciones con el Tutor.';
$string['privacy:metadata:raison:userlastname'] = 'Su apellido se envía para personalizar su experiencia en Raison y facilitar su identificación en sus conversaciones con el Tutor.';
$string['privacy:metadata:raison:userrolename'] = 'Su rol se envía para gestionar sus permisos en Raison.';
$string['privacy:metadata:raison:interaction'] = 'Los registros de sus interacciones, como tutores creados y conversaciones, se envían para mejorar su experiencia.';
$string['localhosterror'] = 'No es posible registrar la instancia de Moodle en Raison porque el sitio se está ejecutando en localhost.';
$string['webservicesenableerror'] = 'No se han podido activar los servicios web.';
$string['restprotocolenableerror'] = 'No se ha podido activar el protocolo REST.';
$string['servicecreationerror'] = 'No se ha podido crear el servicio REST de Raison.';
$string['capabilityassignerror'] = 'No se ha podido asignar la capacidad "{$a}" al rol.';
$string['tokencreationerror'] = 'No se ha podido generar el token REST de Raison.';
$string['installtroubleshoot'] = 'Si encuentra algún problema durante la instalación, consulte la <a href="http://troubleshoot-moodle.raison.is" target="_blank">guía de solución de problemas</a>.';
$string['adhocqueued'] = 'La sincronización con los servicios de Raison debería haber comenzado en su tarea ad-hoc <a href="{$a->adhoc_link}">\local_corolair\task\setup_corolair_connection_task</a>. Si no es así, genere una clave API desde <a href="{$a->trainer_page_link}">aquí</a>.';
$string['raisontuto'] = 'Aprenda a utilizar Raison consultando <a href="http://troubleshoot-moodle.raison.is" target="_blank">este tutorial</a>.';
$string['redirectingmessage'] = 'Si no se redirige automáticamente, haga clic en el botón a continuación para continuar a Raison.';
$string['calendlydemo'] = 'Para poder ayudarte de manera efectiva, primero cuéntanos tu caso de uso y tus necesidades en una llamada de descubrimiento con el equipo de Raison. Una vez entendamos tus requerimientos, nuestros desarrolladores se encargarán de solucionar los problemas de conexión con tu instancia de Moodle. Reserva tu llamada  <strong> <a href="http://discoverycall.raison.is/" target="_blank">aquí</a> </strong>.';
