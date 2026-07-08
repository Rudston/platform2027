<?php

return [
    // Plural type labels (filter pills, breadcrumb, empty-state headings)
    'plural' => [
        'locations'         => 'Locations',
        'theme_communities' => 'Theme Communities',
        'organisations'     => 'Organisations',
        'campaigns'         => 'Campaigns',
        'courses'           => 'Courses',
        'events'            => 'Events',
        'default'           => 'Communities',
    ],

    // Singular type labels (empty-state CTAs, search badges)
    'singular' => [
        'location'     => 'Location',
        'theme'        => 'Theme',
        'organisation' => 'Organisation',
        'campaign'     => 'Campaign',
        'course'       => 'Course',
        'event'        => 'Event',
        'default'      => 'Community',
    ],

    // "Add …" phrases with the article baked in (English article rules don't translate)
    'add_label' => [
        'theme_community'        => 'a Theme Community',
        'organisation_community' => 'an Organisation Community',
        'campaign_community'     => 'a Campaign Community',
        'course_community'       => 'a Course Community',
        'event_community'        => 'an Event Community',
        'default'                => 'a Community',
    ],

    'card' => [
        'members' => ':count members',
    ],

    // Circle lifecycle status shown on cards
    'status_pending' => 'Pending',

    'page' => [
        'title'    => 'Community',
        'back'     => '← Explore Communities',
        'services' => 'Services',
        'members'  => ':count members',
        'join'     => 'Join Community',
    ],

    'add_modal' => [
        'title'       => 'Add :label',
        'placeholder' => 'The form for adding :label will be added here.',
    ],

    // Add-community modal — auth guard, organisation form
    'login_to_add'                   => 'Please log in to add a community to the platform.',
    'organisation_duplicate_warning' => 'An organisation community for this organisation already exists on the platform.',
    'submit_for_approval'            => 'Submit for Approval',
    'contact_person_for_approval'    => 'Contact person for approval',

    'request_modal' => [
        'title'         => 'Request a location in :place',
        'subtitle'      => 'We will let you know once it has been added.',
        'location_name' => 'Location name',
        'send'          => 'Send Request',
    ],
];
