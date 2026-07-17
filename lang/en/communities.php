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
        'title'     => 'Community',
        'back'      => '← Explore Communities',
        'services'  => 'Services',
        'members'   => ':count members',
        'join'      => 'Join Community',
        'admins'    => 'Admins',
        'no_admins' => 'None yet',
        'contact'   => 'Contact',
        'email'     => 'Email',
        'website'   => 'Website',
        'leave'              => 'Leave Community',
        'leave_confirm'      => 'Are you sure you want to leave this community?',
        'add_self_admin'         => 'Add me as Circle Admin',
        'add_self_admin_confirm' => 'Add yourself as an administrator of this community?',
        'remove_self_admin'         => 'Remove me as Circle Admin',
        'remove_self_admin_confirm' => 'Remove yourself as an administrator of this community?',
        'remove_self_admin_sole'    => 'Please appoint a new Circle Admin first.',
        'join_available_at'  => 'You can join from :date',
        'join_modal_title'   => 'Join this community',
        'org_member_question' => 'Are you a member of staff or the board?',
        'swap_prompt'        => "You're at the limit for this type — choose a community to leave:",
        'organisation_members'    => 'Organisation members',
        'no_organisation_members' => 'None yet',
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

    // Organisation add-community form fields
    'org_form' => [
        'organisation_name'   => 'Organisation name',
        'website_url'         => 'Website URL',
        'website_placeholder' => 'https://…',
        'description'         => 'Description',
        'contact_name'        => 'Contact name',
        'contact_email'       => 'Contact email',
        'contact_job_title'   => 'Contact job title',
    ],

    'request_modal' => [
        'title'         => 'Request a location in :place',
        'subtitle'      => 'We will let you know once it has been added.',
        'location_name' => 'Location name',
        'send'          => 'Send Request',
    ],
];
