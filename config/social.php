<?php

/*
| Permissions (OAuth scopes) the Social Distribution "Connect account" buttons ask each network for.
|
| The full set this platform can use is requested, so adding a feature does not mean a code change.
| A network refuses the WHOLE connection if any one of these is not enabled for the app in its
| developer console (Facebook use cases / App Review, LinkedIn products, X app permissions). If a
| network says a scope is invalid, either enable it there or trim the list in .env — no code change:
|
|   FACEBOOK_SCOPES="pages_show_list,pages_manage_posts,business_management"
|   X_SCOPES="tweet.read tweet.write users.read offline.access"
|   LINKEDIN_SCOPES="openid profile email w_member_social"
|
| Facebook separates scopes with commas; X and LinkedIn with spaces.
*/

return [
    /*
    | Network apps configured here rather than in services.php. A LinkedIn company page needs its own
    | LinkedIn app: the Community Management API must be the only product on the app that uses it,
    | so it cannot share the personal-profile app ("Share on LinkedIn" + "Sign In with LinkedIn").
    */
    'apps' => [
        'linkedin_page' => [
            'client_id'     => env('LINKEDIN_PAGE_CLIENT_ID'),
            'client_secret' => env('LINKEDIN_PAGE_CLIENT_SECRET'),
        ],
    ],

    'scopes' => [
        'facebook' => env('FACEBOOK_SCOPES', implode(',', [
            // Identity of the person connecting.
            'public_profile', 'email',
            // Pages: list them, post, read engagement and insights, moderate comments, messages.
            'pages_show_list', 'pages_manage_posts', 'pages_read_engagement', 'pages_read_user_content',
            'pages_manage_engagement', 'pages_manage_metadata', 'pages_messaging', 'read_insights',
            // Pages owned through a Business Manager are only visible with this.
            'business_management',
            // Instagram professional accounts linked to the Page.
            'instagram_basic', 'instagram_content_publish', 'instagram_manage_comments',
            'instagram_manage_insights', 'instagram_manage_messages',
        ])),

        'x' => env('X_SCOPES', implode(' ', [
            'tweet.read', 'tweet.write', 'tweet.moderate.write', 'users.read', 'offline.access', 'media.write',
            'follows.read', 'follows.write', 'like.read', 'like.write', 'list.read', 'list.write',
            'bookmark.read', 'bookmark.write', 'mute.read', 'mute.write', 'block.read', 'block.write', 'space.read',
        ])),

        // The person's own profile. A company page is its own connection (linkedin_page, below).
        'linkedin' => env('LINKEDIN_SCOPES', implode(' ', ['openid', 'profile', 'email', 'w_member_social'])),

        // The company page: post as it, read what its posts earned, and list the pages the person administers.
        'linkedin_page' => env('LINKEDIN_PAGE_SCOPES', implode(' ', ['r_organization_social', 'w_organization_social', 'rw_organization_admin'])),
    ],
];
