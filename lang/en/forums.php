<?php

return [
    'total_groups'       => 'Groups',
    'participants'       => 'Participants',
    'total_discussions'  => 'Discussions',
    'create_group'       => '+ Create Group',
    'search_placeholder' => 'Search groups…',
    'banner_placeholder' => 'Banner',
    'no_groups'          => 'No forum groups yet.',
    'discussions_count'  => ':count discussions',
    'created'            => 'Created :date',

    'filter' => [
        'all'         => 'All',
        'active'      => 'Active',
        'deactivated' => 'Deactivated',
        'archived'    => 'Archived',
    ],

    'actions' => [
        'edit'        => 'Edit',
        'discussions' => 'Discussions',
        'deactivate'  => 'Deactivate Group',
        'manage'      => 'Manage',
    ],

    'deactivate_confirm' => 'Deactivate this group?',

    'status' => [
        'active'      => 'Active',
        'deactivated' => 'Deactivated',
        'archived'    => 'Archived',
    ],

    'visibility' => [
        'public'   => 'Public',
        'private'  => 'Private',
        'internal' => 'Internal',
    ],

    'visibility_help' => [
        'public'   => 'Anyone can view and discover this group. Readonly for visitors.',
        'private'  => 'Only community members can view and discover this group (no visitors).',
        'internal' => 'Only community members having an internal role can view and discover this group.',
    ],

    'access' => [
        'members'  => 'Only community members can participate.',
        'internal' => 'Only community members having an internal role can participate.',
    ],

    'modal' => [
        'create_title'          => 'Create a Forum Group',
        'edit_title'            => 'Edit Forum Group',
        'section_basic'         => 'Basic Information',
        'section_visibility'    => 'Visibility & Access',
        'section_images'        => 'Group Images',
        'name'                  => 'Name',
        'name_placeholder'      => 'Enter group name',
        'slug'                  => 'URL Slug',
        'slug_placeholder'      => 'group-slug',
        'slug_note'             => 'This is used to provide a url for the forum',
        'description'           => 'Description',
        'description_placeholder' => 'Describe what this group is about',
        'group_access'          => 'Group Access',
        'save'                  => 'Save Group',
    ],

    'validation' => [
        'slug_required' => 'Please provide a name or URL slug.',
        'slug_taken'    => 'A group with a similar URL slug already exists in this community.',
    ],

    'back_to_forums'          => '← Back',
    'discussions_coming_soon' => 'Discussions — coming soon.',
];
