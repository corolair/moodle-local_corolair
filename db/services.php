<?php
defined('MOODLE_INTERNAL') || die();

/**
 * External functions exposed by local_corolair.
 */
$functions = [
    'local_corolair_get_section_availability' => [
        'classname'   => 'local_corolair\\external\\get_section_availability',
        'methodname'  => 'execute',
        'description' => 'Return section (topic) availability JSON and metadata.',
        'type'        => 'read',
        'ajax'        => true,
    ],
];

/**
 * Services definitions For Corolair Integration with Moodle.
 */
$services = [
    'Corolair REST Service' => [
        'functions' => [
            'core_user_get_users',
            'core_user_get_users_by_field',
            'core_course_get_courses',
            'core_course_get_contents',
            'mod_resource_get_resources_by_courses',
            'core_enrol_get_users_courses',
            'core_enrol_get_enrolled_users',
            'core_webservice_get_site_info',
            'core_enrol_get_enrolled_users_with_capability',
            'core_course_get_categories',
            'mod_lesson_get_lessons_by_courses',
            'mod_lesson_get_lesson',
            'mod_lesson_get_pages',
            'mod_lesson_get_page_data',
            'local_corolair_get_section_availability',
        ],
        'restrictedusers' => 0,
        'enabled' => 1,
        'shortname' => 'corolair_rest',
        'uploadfiles' => 1,
        'downloadfiles' => 1,
    ],
];
