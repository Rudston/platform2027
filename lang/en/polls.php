<?php

return [
    'back_to_polls' => '← Back',
    'groups_heading' => 'Poll Groups',
    'total_groups' => 'Groups',
    'total_polls' => 'Polls',
    'open_polls' => 'Open now',
    'create_group' => '+ Create Group',
    'search_placeholder' => 'Search groups…',
    'no_groups' => 'No poll groups yet.',
    'no_groups_found' => 'No poll groups found.',
    'no_polls' => 'No polls in this group yet.',
    'polls_count' => ':count polls',
    'created' => 'Created :date',
    'group_intro' => 'Every poll belongs to a group, so a community\'s decisions stay together.',

    'filter' => [
        'all' => 'All',
        'active' => 'Active',
        'archived' => 'Archived',
    ],

    'archive_confirm' => 'Archive this group? Its polls stay listed and findable.',

    // Derived lifecycle — Scheduled/Open/Closed come from the clock, not the
    // stored status (see docs/adr/0001).
    'state' => [
        'draft' => 'Draft',
        'scheduled' => 'Scheduled',
        'open' => 'Open',
        'closed' => 'Closed',
        'concluded' => 'Ended early',
        'cancelled' => 'Cancelled',
    ],

    'state_hint' => [
        'draft' => 'Not yet published.',
        'scheduled' => 'Opens :date.',
        'open' => 'Closes :date.',
        'closed' => 'Closed :date.',
        'concluded' => 'Ended early by an organiser.',
        'cancelled' => 'Voided — these responses are never counted.',
    ],

    'turnout' => ':responded of :electorate responded',
    'turnout_pending' => ':responded responded so far',
    'archived_badge' => 'Archived',

    'create_poll' => '+ Create Poll',
    'back_to_group' => '← Back to group',

    'poll' => [
        'create_title' => 'Create Poll',
        'edit_title' => 'Edit Poll',
        'title' => 'Title',
        'title_placeholder' => 'e.g. Choose a Circle Admin',
        'description' => 'Description',
        'description_placeholder' => 'Any context a respondent needs.',
        'prompt' => 'Prompt',
        'prompt_placeholder' => 'e.g. Select ONE from:',
        'prompt_help' => 'The instruction shown above the options.',
        'shape' => 'How do people answer?',
        'tally_method' => 'How are answers counted?',
        'tally_help' => 'Only the methods valid for the chosen answer style are offered.',
        'options' => 'Options',
        'option_placeholder' => 'Candidate or proposal',
        'add_option' => '+ Add option',
        'remove_option' => 'Remove',
        'min_options' => 'A poll needs at least two options.',
        'eligibility' => 'Who may respond?',
        'rating_scale' => 'Rating scale',
        'no_rating_scales' => 'No rating scales have been set up on the platform yet.',
        'require_full_ranking' => 'Require every option to be ranked',
        'allow_response_update' => 'Let respondents change their answer while the poll is open',
        'hide_voter_identities' => 'Hide who chose what',
        'hide_help' => 'Results show totals only. Identity is still stored — this is not a secret ballot.',
        'publish_results' => 'Publish the result outside this community once it closes',
        'opens_at' => 'Opens',
        'closes_at' => 'Closes',
        'qualifying_date' => 'Membership cut-off',
        'qualifying_help' => 'Only members who joined on or before this date may respond. Defaults to the moment you publish; set it earlier so joining after the poll was announced confers no vote.',
        'save' => 'Save Poll',
    ],

    'shape' => [
        'single_choice' => 'Pick one',
        'ranked_choice' => 'Rank them in order',
        'rating' => 'Score each one',
    ],

    'method' => [
        'plurality' => 'Most votes wins',
        'instant_runoff' => 'Instant runoff',
        'average_score' => 'Highest average score',
    ],

    'eligibility_option' => [
        'private' => 'Any member of this community',
        'internal' => 'Members with a confirmed internal role',
    ],

    'respond' => [
        'heading' => 'Your response',
        'submit' => 'Submit response',
        'update' => 'Change my response',
        'submitted' => 'Your response has been recorded.',
        'already' => 'You have responded. Your answer is not shown to anyone else.',
        'locked' => 'You have responded. This poll does not allow changes.',
        'not_open' => 'This poll is not accepting responses.',
        'not_eligible' => 'You are not eligible to respond to this poll.',
        'not_eligible_late' => 'Only members who had joined by the membership cut-off may respond.',
        'left_circle' => 'You are no longer a member of this community, so you cannot respond.',
        'rank_label' => 'Rank',
        'rank_none' => '—',
        'your_choice' => 'You chose',
    ],

    'result' => [
        'heading' => 'Result',
        'pending' => 'The result will be published when this poll closes.',
        'none' => 'This poll was cancelled, so it has no result.',
        'winner' => 'Winner',
        'tie' => 'Tied — no winner could be declared.',
        'no_responses' => 'Nobody responded.',
        'turnout' => ':responded of :electorate responded',
        'frozen' => 'Frozen :date. Recomputing checks this result; it never replaces it.',
        'totals_heading' => 'Totals',
        'irv_note' => 'These are FIRST-PREFERENCE counts, so the winner is not always the highest number. Lower-placed options were eliminated and their ballots passed to the next preference — this took :rounds rounds.',
        'average_note' => 'These are average scores, so they do not add up to the turnout.',
        'live' => 'Running count',
        'live_note' => 'This poll is still open, so these figures will change.',
        'roster_heading' => 'Who responded',
        'roster_note' => 'Who took part is not a secret; what each person chose is.',
    ],

    'actions' => [
        // Group actions
        'edit' => 'Edit',
        'polls' => 'Polls',
        'archive' => 'Archive Group',
        'restore' => 'Restore Group',
        // Poll lifecycle actions
        'publish' => 'Publish',
        'publish_confirm' => 'Publish this poll? Its electorate is fixed at this moment and cannot be changed afterwards.',
        'conclude' => 'End now',
        'conclude_confirm' => 'End this poll now? It will be counted and the result frozen.',
        'cancel_poll' => 'Cancel poll',
        'cancel_confirm' => 'Cancel this poll? Its responses will never be counted and it will have no result.',
    ],

    'group' => [
        'name' => 'Name',
        'name_placeholder' => 'e.g. 2027 Budget Consultation',
        'slug' => 'URL slug',
        'description' => 'Description',
        'description_placeholder' => 'What is this set of polls for?',
        'save' => 'Save Group',
        'create_title' => 'Create Poll Group',
        'edit_title' => 'Edit Poll Group',
        'slug_taken' => 'A group with a similar name already exists in this community.',
    ],
];
