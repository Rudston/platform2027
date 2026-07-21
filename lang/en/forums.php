<?php

return [
    'groups_heading'     => 'Forum Groups',
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
        // Discussion create modal
        'discussion_title'      => 'Create a Discussion',
        'title_label'           => 'Title',
        'title_placeholder'     => 'Enter discussion title',
        'discussion_slug_note'  => 'This is used to provide a url for the discussion',
        'content_label'         => 'Content',
        'content_placeholder'   => 'Write the first post…',
        'save_discussion'       => 'Save Discussion',
    ],

    'validation' => [
        'slug_required' => 'Please provide a name or URL slug.',
        'slug_taken'    => 'A group with a similar URL slug already exists in this community.',
        'discussion_slug_required' => 'Please provide a title or URL slug.',
        'discussion_slug_taken'    => 'A discussion with a similar URL slug already exists in this group.',
    ],

    'back_to_forums'          => '← Back',
    'discussions_coming_soon' => 'Discussions — coming soon.',

    // Discussions (Phase 1: list / detail / create / join)
    'discussions_heading'      => 'Discussions',
    'create_discussion'        => '+ Create Discussion',
    'no_discussions'           => 'No discussions yet.',
    'by_author'                => 'by :author',
    'back_to_discussions'      => '← Back to discussions',
    'participant_count'        => ':count participants',
    'edited'                   => '(Edited)',
    'edit_post'                => 'Edit',
    'save_post'                => 'Save',
    'responses_heading'        => 'Responses',
    'no_responses'             => 'No responses yet.',
    'post'                     => 'Post',
    'post_response_placeholder' => 'Post a response…',
    'reply'                    => 'Reply',
    'reply_placeholder'        => 'Write a reply…',
    'replying_to'              => 'replying to :author',

    'badge' => [
        'pinned' => 'Pinned',
        'locked' => 'Locked',
    ],
];
