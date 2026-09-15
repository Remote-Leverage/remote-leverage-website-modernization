<?php

/**
 * Title: Full Page - Hiring Manager and Account Executive Checklists
 * Slug: remote-leverage/hmchecklists
 * Categories: remote-leverage
 * Description: Production's /hmchecklists/ internal ops tool — the hiring-manager and account-executive daily, situational and FAQ checklists, posting to the same Zapier catch hook.
 */

/*
 * Internal ops tool, migrated from production /hmchecklists/ on 2026-09-15. Staff use these
 * daily, so every form below keeps production's action, method, target and field names
 * byte for byte — a renamed input silently breaks the downstream automation. Checklist
 * copy is production's verbatim; only the presentation is re-cut onto theme tokens.
 *
 * Webhook: https://hooks.zapier.com/hooks/catch/10540636/2gyrtr0
 * Upsell inquiry (a second hook, fired from JS alongside checklist-form-1600): https://hooks.zapier.com/hooks/catch/26138149/u03014q/
 * Hiring-manager jobs loader: https://n8n.srv1338052.hstgr.cloud/webhook/hm/jobs
 * "Send To Do to Slack" button: https://n8n.srv1338052.hstgr.cloud/webhook/send-todo-to-slack
 */

$wrap = 'w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8';
$h1 = 'Hiring Manager and Account Executive Checklists';
$warning = '⚠️⚠️⚠️ REFRESH BEFORE USING ⚠️⚠️⚠️';

// Nesting mirrors production's indented sub-steps; capped at three levels so a deep list
// cannot push the page into horizontal scroll on a phone.
$indent = ['', 'ml-4 sm:ml-6', 'ml-8 sm:ml-12', 'ml-12 sm:ml-16'];

// Production highlights a handful of steps in green/red/amber. Same signal, theme tokens.
$tones = [
    '' => 'hover:bg-bg-light',
    'success' => 'border border-status-success/40 bg-status-success/10',
    'danger' => 'border border-cross-red/40 bg-cross-red/10',
    'warning' => 'border border-status-warning/50 bg-status-warning/15',
    'info' => 'border border-black/10 bg-bg-map',
];

$nodes = [
    [
        'kind' => 'heading',
        'text' => 'Daily (required at least once)',
    ],
    [
        'kind' => 'checklist',
        'title' => 'Start of Day',
        'blocks' => [
            [
                'kind' => 'note',
                'depth' => 0,
                'html' => 'Hiring Manager Name:',
            ],
            [
                'kind' => 'text',
                'name' => 'hiringManagerFirstName',
                'placeholder' => 'First name...',
            ],
            [
                'kind' => 'text',
                'name' => 'hiringManagerLastName',
                'placeholder' => 'Last name...',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Review Interviews Completed stage',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item1',
                'html' => 'Open and review each ticket',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item2',
                'html' => 'Write down next steps in a To-Do list to get it moving to next step',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item3',
                'html' => 'Make sure all tickets are in the appropriate stage',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Review Second Round of Interviews stage',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item4',
                'html' => 'Open and review each ticket',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item5',
                'html' => 'Write down next steps in a To-Do list to get it moving to next step',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item6',
                'html' => 'Make sure that all tickets&#46;&#46;&#46;',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'item6a',
                'html' => '&#46;&#46;&#46; have the <strong>Interview Date &#45; Time</strong> field updated',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'item6b',
                'html' => '&#46;&#46;&#46; are in the appropriate stage',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Review Interview Scheduled stage',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item7',
                'html' => 'Open each ticket',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item8',
                'html' => 'Check your Google Calendar and ensure VAs have been invited',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'item8a',
                'html' => 'If not, reach out to your Senior Recruiter on Slack to make sure she&#39;s actively working on it',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item9',
                'html' => 'Remove any declined VAs and alert recruiter to add replacements',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item10',
                'html' => 'Write down who you removed in the job notes so they don&#39;t add them again to the same job',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item11',
                'html' => 'Write down next steps in a To-Do list to get jobs moving to next step',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item11_date',
                'html' => 'Make sure all tickets have <strong>Interview Date &#45; Time</strong> field updated',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item12',
                'html' => 'Make sure all tickets are in the appropriate stage',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Review Final Recruiter Approved &#45; Phase 2 stage',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item16',
                'html' => 'Open each ticket',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item17',
                'html' => 'Write down next steps in a To-Do list to get it moving to next step',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item18',
                'html' => 'Make sure all tickets are in the appropriate stage',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Review Hiring Form Submitted stage',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item19',
                'html' => 'Review all tickets and alert your Recruiter if you notice a ticket is not moving fast enough',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item20',
                'html' => 'Make sure all tickets are in the appropriate stage',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Review Missed Onboarding Meeting stage',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item21',
                'html' => 'Open each ticket',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item22',
                'html' => 'Write down next steps in a To-Do list to get it moving to next step',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item23',
                'html' => 'Make sure all tickets are in the appropriate stage',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Review Onboarding Booked stage',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item24',
                'html' => 'Double check calendar to make sure they&#39;re still on there',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item25',
                'html' => 'Make sure all tickets are in the appropriate stage',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Review Onboarding NOT Booked stage',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item26',
                'html' => 'Open each ticket',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item27',
                'html' => 'Write down next steps in a To-Do list to get it moving to next step',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item28',
                'html' => 'Make sure all tickets are in the appropriate stage',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Review On Hold stage',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item29',
                'html' => 'Quickly glance over your tickets to see if you&#39;ll follow up today',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item30',
                'html' => 'Make sure all tickets are in the appropriate stage',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Review Emails',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item31',
                'html' => 'Review emails in your Gmail inbox and respond using Abbas&#39; Email Protocol <a href="https://www.loom.com/share/b9ba0c8de0be427293bbad4c2853992b?utm_source=facebook&amp;utm_medium=paid-social&amp;utm_term=virtual%20appointment%20setter&amp;utm_content=dc6&amp;utm_campaign=broad-5k-10k&amp;gclid=CjwKCAjw59q2BhBOEiwAKc0ijQO-Uf219bGWCrLBcwpO4957_yhDvvmV35dyoZXVztB21Zk5iO7rCBoCAggQAvD_BwE&amp;sid=c7006c26-21f3-4ce8-88a6-4662cd13c637" target="_blank" rel="noopener">here</a>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item31a',
                'html' => 'Get your Gmail inbox down to 0',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'item31a1',
                'html' => 'Remove any notifications for events and make sure to respond to any emails you&#39;ve gotten since you clocked out last night',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Review Quo',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item32',
                'html' => 'Review Quo texts and calls to ensure you got back to everyone',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item33',
                'html' => 'Check TODAY and TOMORROW&#39;S interviews on your Calendar to ensure VAs have been invited and accepted the invitation, and if not, tell the recruiter to reach out to them or replace them with other candidates quickly',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Daily Team Update',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item34',
                'html' => 'Post your To Do list for today in the #operations chat on Slack. Use the format below; remove any sections that don&#39;t have any info for the day',
                'value' => 'checked',
            ],
            [
                'kind' => 'select',
                'id' => 'hm-jobs-select',
                'label' => 'Load jobs by hiring manager:',
                'options' => [
                    [
                        'value' => '',
                        'text' => '— select a hiring manager —',
                    ],
                    [
                        'value' => 'Andrea Gomez',
                        'text' => 'Andrea Gomez',
                    ],
                    [
                        'value' => 'Anthony Sanchez',
                        'text' => 'Anthony Sanchez',
                    ],
                    [
                        'value' => 'Bryan Muñoz',
                        'text' => 'Bryan Muñoz',
                    ],
                    [
                        'value' => 'Caprice Jorge',
                        'text' => 'Caprice Jorge',
                    ],
                    [
                        'value' => 'Carla Stivala',
                        'text' => 'Carla Stivala',
                    ],
                    [
                        'value' => 'Christy Bodden',
                        'text' => 'Christy Bodden',
                    ],
                    [
                        'value' => 'Dave Mejia',
                        'text' => 'Dave Mejia',
                    ],
                    [
                        'value' => 'David Ibarra',
                        'text' => 'David Ibarra',
                    ],
                    [
                        'value' => 'Diego Barrera',
                        'text' => 'Diego Barrera',
                    ],
                    [
                        'value' => 'Gabriel Rocha',
                        'text' => 'Gabriel Rocha',
                    ],
                    [
                        'value' => 'Harry Thorne',
                        'text' => 'Harry Thorne',
                    ],
                    [
                        'value' => 'Ivan Isaza',
                        'text' => 'Ivan Isaza',
                    ],
                    [
                        'value' => 'Ivan Miranda',
                        'text' => 'Ivan Miranda',
                    ],
                    [
                        'value' => 'Jack O\'Hara',
                        'text' => 'Jack O&#39;Hara',
                    ],
                    [
                        'value' => 'Manny Sabogal',
                        'text' => 'Manny Sabogal',
                    ],
                    [
                        'value' => 'Mario Hernandez',
                        'text' => 'Mario Hernandez',
                    ],
                    [
                        'value' => 'Matt Jarboe',
                        'text' => 'Matt Jarboe',
                    ],
                    [
                        'value' => 'Melania Toledo',
                        'text' => 'Melania Toledo',
                    ],
                    [
                        'value' => 'Miguel Quintero',
                        'text' => 'Miguel Quintero',
                    ],
                    [
                        'value' => 'Natasha Jaimes',
                        'text' => 'Natasha Jaimes',
                    ],
                    [
                        'value' => 'Princess Cruz',
                        'text' => 'Princess Cruz',
                    ],
                    [
                        'value' => 'Samantha Pietersz',
                        'text' => 'Samantha Pietersz',
                    ],
                    [
                        'value' => 'Sebastian Canahuati',
                        'text' => 'Sebastian Canahuati',
                    ],
                    [
                        'value' => 'Skylar Minick',
                        'text' => 'Skylar Minick',
                    ],
                    [
                        'value' => 'Suhey Santiago',
                        'text' => 'Suhey Santiago',
                    ],
                    [
                        'value' => 'Xavier Larrea',
                        'text' => 'Xavier Larrea',
                    ],
                ],
            ],
            [
                'kind' => 'textarea',
                'name' => 'dailyTeamUpdate',
                'id' => 'dailyTeamUpdateText',
                'value' => 'To Do

Payments to Collect
* 01/01 Sam Thornal $4.978 | 3/3

Hiring Form Submitted
* 12/29 (3B/2) Jessica Uriostegui (EA) | batch 3
* 12/29 Andreina Martinez (Sales)

Awaiting Decision
* 12/22 (R) Simon Golub (Admin) | traveling for the holidays | FU 12/29

Client Onboarding
* 12/29 Ben Gnatyuk (Appt Setter) @ 3.30pm PST
* 12/30 Apostle Jordan Foré (EA) @ 8am PST

Client Onboarding NOT Booked
* 12/23 Steve Adams (Cold Caller) | waiting on client to reschedule
* 12/29 Jason Roland | waiting on client to schedule

Interviews Scheduled
* 12/30 💰 Taylor Price (SM Manager) @ 4pm PST
* 01/02 💰 (3B/1) Edgar Larios (Admin) @ 1pm PST
* 01/05 💰 Deepak Hemrajani (SM Manager) @ 7am PST

On Hold - Follow Up (posting these on Mondays)
* 11/24 Brett Williamson (Setter)
   * Client will schedule interviews for the following week on Dec 23. When he does, I\'ll send the ticket back to HFS
* 12/01 Omar Camacho
   * Unresponsive since no-showing to onboarding on 11/25 | sent follow-up email and SMS | last follow-up 12/08 | FU 01/02
* 12/04 (3B) Justin Rivas (Marketing)
   * Unresponsive since 11/24 | followed-up once again on Dec 8 | FU 01/02
* 12/08 Alexandra Nickolai (EA Bookkeeper)
   * No-showed to onboarding on 12/03 | unresponsive since | sent follow-up 12/08 | FU 01/02',
            ],
            [
                'kind' => 'slackButton',
                'label' => 'Send To Do to Slack',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Client Interview Reminders',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item35',
                'tone' => 'success',
                'html' => 'Send clients a reminder for today&#39;s interview: include time in THEIR timezone, number of VAs scheduled, and a friendly note',
                'value' => 'checked',
            ],
        ],
        'form' => [
            'id' => 'checklist-form-1',
            'action' => 'https://hooks.zapier.com/hooks/catch/10540636/2gyrtr0',
            'target' => 'hidden_iframe_1',
            'checklist_name' => 'Start of Day Checklist',
            'require_name' => false,
        ],
    ],
    [
        'kind' => 'checklist',
        'title' => 'Daily Follow-up',
        'blocks' => [
            [
                'kind' => 'note',
                'depth' => 0,
                'html' => 'Hiring Manager Name:',
            ],
            [
                'kind' => 'text',
                'name' => 'hiringManagerFirstName',
                'placeholder' => 'First name...',
            ],
            [
                'kind' => 'text',
                'name' => 'hiringManagerLastName',
                'placeholder' => 'Last name...',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Missed Onboarding',
                'emoji' => '►',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Live Updates',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'mo_sub_item1',
                'html' => '<strong>Day 1:</strong> Call + email <em>Hyperlink &quot;here&quot; in the email and add your Onboarding + Applicant Criteria link</em>',
                'value' => 'checked',
                'template_id' => 'mo-email-day1',
            ],
            [
                'kind' => 'template',
                'id' => 'mo-email-day1',
                'html' => '<strong>Subject:</strong> [Remote Leverage] Missed onboarding call <br> Hi CLIENT, <br> We were scheduled to connect earlier today for the onboarding call to align on your VA needs and role expectations, but it looks like we missed you. <br> This conversation is key so we can clearly define what you&#39;re looking for and move forward with sourcing and/or next steps efficiently. <br> Please select a new time <a href="https://remoteleverage.com/hmchecklists/" target="_blank" rel="noopener">here</a> so we can get this rescheduled as soon as possible. <br> Best,',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'mo_sub_item1a',
                'html' => 'Change <strong>Job Status</strong> to <strong>Missed Onboarding Meeting</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'mo_sub_item1b',
                'html' => 'Post an update in #operations on Slack and hyperlink the client&#39;s name to the Job',
                'value' => 'checked',
            ],
            [
                'kind' => 'note',
                'depth' => 2,
                'html' => ':red_circle: Client Update: CLIENT Day 1. No-showed to onboarding call. Called and emailed to reschedule.',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'mo_sub_item2',
                'html' => '<strong>Day 2:</strong> Call + text + email <em>Find the Day 1 email and reply to it. No need to write a new email thread.</em> <em>Hyperlink &quot;here&quot; in the email and add your Onboarding + Applicant Criteria link</em>',
                'value' => 'checked',
                'template_id' => 'mo-email-day2',
            ],
            [
                'kind' => 'template',
                'id' => 'mo-email-day2',
                'html' => 'Hi CLIENT, <br> I just tried calling you but was unable to reach you. <br> We need to complete the onboarding call to align on your VA requirements before we can continue the hiring process. Delaying this puts the role on pause on our end. <br> Please select a time <a href="https://remoteleverage.com/hmchecklists/" target="_blank" rel="noopener">here</a> so we can move things forward. <br> Let me know where things stand. <br> Best,',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'mo_sub_item2a',
                'html' => 'Change <strong>Job Status</strong> to <strong>On Hold – Follow Up</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'mo_sub_item2b',
                'html' => 'Post an update in #operations on Slack and hyperlink the client&#39;s name to the Job',
                'value' => 'checked',
            ],
            [
                'kind' => 'note',
                'depth' => 2,
                'html' => ':ghost: Client Update: CLIENT Day 2. Still unresponsive after onboarding no-show. Called, texted, and emailed. Ticket placed on OH-FU',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'mo_sub_item3',
                'html' => '<strong>Day 3:</strong> Call + text + email <em>Reply to the Day 2 email to keep a single thread.</em> <em>Hyperlink &quot;here&quot; in the email and add your Onboarding + Applicant Criteria link</em>',
                'value' => 'checked',
                'template_id' => 'mo-email-day3',
            ],
            [
                'kind' => 'template',
                'id' => 'mo-email-day3',
                'html' => 'Hi CLIENT, <br> I&#39;ve tried reaching out several times following the missed onboarding call and haven&#39;t heard back. <br> Without completing this onboarding call, we&#39;re unable to continue moving forward. If we don&#39;t hear back by end of day today, we&#39;ll pause the process. <br> Please let me know how you&#39;d like to proceed. <br> Best,',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'mo_sub_item3a',
                'html' => 'Post an update in #operations on Slack and hyperlink the client&#39;s name to the Job',
                'value' => 'checked',
            ],
            [
                'kind' => 'note',
                'depth' => 2,
                'html' => ':ghost: Client Update: CLIENT Day 3. Still unresponsive after onboarding no-show. Called, texted, and emailed.',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'mo_sub_item5',
                'html' => '<strong>Day 5:</strong> Call + text + email <em>Reply to the Day 3 email to keep a single thread.</em>',
                'value' => 'checked',
                'template_id' => 'mo-email-day5',
            ],
            [
                'kind' => 'template',
                'id' => 'mo-email-day5',
                'html' => 'Hi CLIENT, <br> Since we haven&#39;t heard back following the missed onboarding call, we&#39;re placing this role On Hold for now. <br> Once you&#39;re ready to reconnect and complete the onboarding intake, just let me know and we&#39;ll restart the process. <br> Best,',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'mo_sub_item5a',
                'html' => 'Change <strong>Job Status</strong> to <strong>Unresponsive Before Onboarding</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'mo_sub_item5b',
                'html' => 'Post an update in #operations on Slack and hyperlink the client&#39;s name to the Job',
                'value' => 'checked',
            ],
            [
                'kind' => 'note',
                'depth' => 2,
                'html' => ':ghost: Client Update: CLIENT Day 5. Still unresponsive after onboarding no-show. Called, texted, and emailed. Ticket moved to On Hold.',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Interviews Completed: Awaiting Decision',
                'emoji' => '►',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Live Updates',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'dfu_sub_item1',
                'html' => '<strong>Day 1:</strong> Call + email <em>Hyperlink &quot;here&quot; in the email and add your Remote Leverage &#45; Follow Up Call link</em>',
                'value' => 'checked',
                'template_id' => 'dfu-email-day1',
            ],
            [
                'kind' => 'template',
                'id' => 'dfu-email-day1',
                'html' => '<strong>Subject:</strong> [Remote Leverage] Quick check-in after interviews <br> Hi CLIENT, <br> Following up on yesterday&#39;s interviews. Strong candidates move fast, and top picks can get picked up by others if decisions take too long. <br> Let&#39;s connect soon to discuss feedback and next steps. The sooner we finalize this, the better we can secure your top choice. Please select a date and time <a href="" target="_blank" rel="noopener">here</a>. <br> Excited to hear your thoughts. <br> Best,',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'dfu_sub_item1a',
                'html' => 'Post an update in #operations on Slack and hyperlink the client&#39;s name to the Job',
                'value' => 'checked',
            ],
            [
                'kind' => 'note',
                'depth' => 2,
                'html' => ':warning: Client Update: CLIENT Day 1. Unresponsive after interviews. Called, texted, and emailed.',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'dfu_sub_item2',
                'html' => '<strong>Day 2:</strong> Call + text + email <em>Hyperlink &quot;here&quot; in the email and add your Remote Leverage &#45; Follow Up Call link</em>',
                'value' => 'checked',
                'template_id' => 'dfu-email-day2',
            ],
            [
                'kind' => 'template',
                'id' => 'dfu-email-day2',
                'html' => '<strong>Subject:</strong> [Remote Leverage] Decision timing on interviewed candidates <br> Hi CLIENT, <br> I tried giving you a quick call earlier but didn&#39;t catch you. <br> Following up on the interviews once again. The candidates you met are moving quickly with other clients, and we risk losing a top choice if a decision is delayed. <br> Please select a date and time <a href="" target="_blank" rel="noopener">here</a>. <br> Let me know where things stand. <br> Best,',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'dfu_sub_item2a',
                'html' => 'Change <strong>Job Status</strong> to <strong>On Hold &#45; Follow Up</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'dfu_sub_item2b',
                'html' => 'Post an update in #operations on Slack and hyperlink the client&#39;s name to the Job',
                'value' => 'checked',
            ],
            [
                'kind' => 'note',
                'depth' => 2,
                'html' => ':ghost: Client Update: CLIENT Day 2. Unresponsive after interviews. Called, texted, and emailed. Ticket placed on OH-FU',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'dfu_sub_item3',
                'html' => '<strong>Day 3:</strong> Call + text + email',
                'value' => 'checked',
                'template_id' => 'dfu-email-day3',
            ],
            [
                'kind' => 'template',
                'id' => 'dfu-email-day3',
                'html' => '<strong>Subject:</strong> [Remote Leverage] Final follow-up; candidate availability <br> Hi CLIENT, <br> I tried calling again today but haven&#39;t been able to connect. <br> At this point, we can&#39;t continue holding the candidates you interviewed. They are receiving interest elsewhere, and if we don&#39;t hear back by end of day today, we&#39;ll need to release them to other opportunities. <br> Best,',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'dfu_sub_item3_symbo',
                'html' => 'Call the client using Symbo instead of Quo',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 3,
                'name' => 'dfu_sub_item3_symbo1',
                'html' => 'Go to <a href="https://app.symbo.ai/login" target="_blank" rel="noopener">Symbo</a>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 3,
                'name' => 'dfu_sub_item3_symbo2',
                'html' => 'Username: spectator@remoteleverage.com | Password: !Remote2025',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 3,
                'name' => 'dfu_sub_item3_symbo3',
                'html' => 'Click on the 📞 icon on the top-right corner and type in the client&#39;s phone number <em>i.e. +16503745574</em>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 3,
                'name' => 'dfu_sub_item3_symbo4',
                'html' => 'Click on <strong>Call</strong> at the bottom of the dialer',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'dfu_sub_item3a',
                'html' => 'If no response by EoD, remove VAs from the ticket and add (client unresponsive) after each name in <strong>Candidate Vetting Updates</strong> note below <strong><u>Removed</u></strong> <em>i.e. 02/11 VI &#45; Matt Itties (client unresponsive)</em>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'dfu_sub_item3b',
                'html' => 'Email each VA to inform them. Use the following template',
                'value' => 'checked',
                'template_id' => 'dfu-email-day3-va',
            ],
            [
                'kind' => 'template',
                'id' => 'dfu-email-day3-va',
                'html' => '<strong>Subject:</strong> Status Update: Interview Process with CLIENTFIRSTNAME <br> Hi VANAME, <br> We wanted to let you know that CLIENTFIRSTNAME has decided to pause their hiring plans for the time being. In the meantime, we&#39;ll continue assigning you to other clients, so please keep an eye on your email and calendar for upcoming interview invitations. <br> Thank you for your understanding. <br> Best,',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'dfu_sub_item3c',
                'html' => 'Post an update in #operations on Slack and hyperlink the client&#39;s name to the Job. Tag the recruiter in the Job',
                'value' => 'checked',
            ],
            [
                'kind' => 'note',
                'depth' => 2,
                'html' => ':ghost: Client Update: CLIENT Day 3. Ghosting after interviews. Called, texted, and emailed. Released and informed VAs @RECRUITER',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'dfu_sub_item5',
                'html' => '<strong>Day 5:</strong> Call + text + email',
                'value' => 'checked',
                'template_id' => 'dfu-email-day5',
            ],
            [
                'kind' => 'template',
                'id' => 'dfu-email-day5',
                'html' => '<strong>Subject:</strong> [Remote Leverage] Hiring process status update <br> Hi CLIENT, <br> We haven&#39;t heard back since the interviews, so we&#39;re going to place this role On Hold for now. <br> When you&#39;re serious about moving forward with a hire, just let me know and we&#39;ll be happy to restart the process and present new candidates. <br> In the meantime, we&#39;ll pause outreach and interviews on our end. <br> Best,',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'dfu_sub_item5a',
                'html' => 'Change <strong>Job Status</strong> to <strong>On Hold</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'dfu_sub_item5b',
                'html' => 'Post an update in #operations on Slack and hyperlink the client&#39;s name to the Job. Tag the recruiter in the Job',
                'value' => 'checked',
            ],
            [
                'kind' => 'note',
                'depth' => 2,
                'html' => ':ghost: Client Update: CLIENT Day 5. Still ghosting. Called, texted, and emailed. Moved ticket to On Hold. @RECRUITER',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Second Round of Interviews',
                'emoji' => '►',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Live Updates',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'sri_sub_item1',
                'html' => '<strong>Day 1:</strong> Call + email <em>Hyperlink &quot;here&quot; in the email and add your Remote Leverage &#45; Follow Up Call link</em>',
                'value' => 'checked',
                'template_id' => 'sri-email-day1',
            ],
            [
                'kind' => 'template',
                'id' => 'sri-email-day1',
                'html' => '<strong>Subject:</strong> [Remote Leverage] Next steps: second round of interviews <br> Hi CLIENT, <br> Following up on the first round of interviews. Based on the feedback so far, we&#39;d like to move forward with a second round to help you finalize your decision. <br> Strong candidates are in high demand, and moving quickly helps us secure your top choice before they&#39;re picked up elsewhere. Let&#39;s connect to align on next steps. Please select a date and time <a href="" target="_blank" rel="noopener">here</a>. <br> Excited to move this forward with you. <br> Best,',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'sri_sub_item1a',
                'html' => 'Post an update in #operations on Slack and hyperlink the client&#39;s name to the Job',
                'value' => 'checked',
            ],
            [
                'kind' => 'note',
                'depth' => 2,
                'html' => ':warning: Client Update: CLIENT Day 1. Unresponsive re: second round scheduling. Called and emailed.',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'sri_sub_item2',
                'html' => '<strong>Day 2:</strong> Call + text + email <em>Reply to the Day 1 email to keep a single thread. Hyperlink &quot;here&quot; and add your Follow Up Call link.</em>',
                'value' => 'checked',
                'template_id' => 'sri-email-day2',
            ],
            [
                'kind' => 'template',
                'id' => 'sri-email-day2',
                'html' => 'Hi CLIENT, <br> I tried giving you a quick call earlier but wasn&#39;t able to reach you. <br> Circling back on the second round of interviews. The candidates from the first round are still available, but that won&#39;t last long as they&#39;re actively interviewing with others. <br> Please select a date and time <a href="" target="_blank" rel="noopener">here</a> so we can lock this in. <br> Let me know where things stand. <br> Best,',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'sri_sub_item2a',
                'html' => 'Change <strong>Job Status</strong> to <strong>On Hold &#45; Follow Up</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'sri_sub_item2b',
                'html' => 'Post an update in #operations on Slack and hyperlink the client&#39;s name to the Job',
                'value' => 'checked',
            ],
            [
                'kind' => 'note',
                'depth' => 2,
                'html' => ':ghost: Client Update: CLIENT Day 2. Unresponsive re: second round scheduling. Called, texted, and emailed. Ticket placed on OH-FU',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'sri_sub_item3',
                'html' => '<strong>Day 3:</strong> Call + text + email <em>Reply to the Day 2 email to keep a single thread.</em>',
                'value' => 'checked',
                'template_id' => 'sri-email-day3',
            ],
            [
                'kind' => 'template',
                'id' => 'sri-email-day3',
                'html' => '<strong>Subject:</strong> [Remote Leverage] Final follow-up: second round candidates <br> Hi CLIENT, <br> I tried calling again today but haven&#39;t been able to connect. <br> We&#39;re at the point where we can no longer hold the candidates from the first round for a second interview. If we don&#39;t hear back by end of day today, we&#39;ll need to release them to other opportunities. <br> Please let me know how you&#39;d like to proceed. <br> Best,',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'sri_sub_item3_symbo',
                'html' => 'Call the client using Symbo instead of Quo',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 3,
                'name' => 'sri_sub_item3_symbo1',
                'html' => 'Go to <a href="https://app.symbo.ai/login" target="_blank" rel="noopener">Symbo</a>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 3,
                'name' => 'sri_sub_item3_symbo2',
                'html' => 'Username: spectator@remoteleverage.com | Password: !Remote2025',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 3,
                'name' => 'sri_sub_item3_symbo3',
                'html' => 'Click on the 📞 icon on the top-right corner and type in the client&#39;s phone number <em>i.e. +16503745574</em>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 3,
                'name' => 'sri_sub_item3_symbo4',
                'html' => 'Click on <strong>Call</strong> at the bottom of the dialer',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'sri_sub_item3a',
                'html' => 'If no response by EoD, remove VAs from the ticket and add (client unresponsive &#45; 2nd round) after each name in <strong>Candidate Vetting Updates</strong> note below <strong><u>Removed</u></strong> <em>i.e. 02/11 VI &#45; Matt Itties (client unresponsive &#45; 2nd round)</em>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'sri_sub_item3b',
                'html' => 'Email each VA to inform them. Use the following template',
                'value' => 'checked',
                'template_id' => 'sri-email-day3-va',
            ],
            [
                'kind' => 'template',
                'id' => 'sri-email-day3-va',
                'html' => '<strong>Subject:</strong> Status Update: Second Round with CLIENTFIRSTNAME <br> Hi VANAME, <br> We wanted to update you that CLIENTFIRSTNAME has paused their hiring process and will not be moving forward with a second round of interviews at this time. We&#39;ll continue assigning you to other clients, so please keep an eye on your email and calendar for upcoming opportunities. <br> Thank you for your patience and understanding. <br> Best,',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'sri_sub_item3c',
                'html' => 'Post an update in #operations on Slack and hyperlink the client&#39;s name to the Job. Tag the recruiter in the Job',
                'value' => 'checked',
            ],
            [
                'kind' => 'note',
                'depth' => 2,
                'html' => ':ghost: Client Update: CLIENT Day 3. Ghosting re: second round scheduling. Called, texted, and emailed. Released and informed VAs @RECRUITER',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'sri_sub_item5',
                'html' => '<strong>Day 5:</strong> Call + text + email <em>Reply to the Day 3 email to keep a single thread.</em>',
                'value' => 'checked',
                'template_id' => 'sri-email-day5',
            ],
            [
                'kind' => 'template',
                'id' => 'sri-email-day5',
                'html' => '<strong>Subject:</strong> [Remote Leverage] Hiring process status update <br> Hi CLIENT, <br> Since we haven&#39;t been able to connect regarding the second round of interviews, we&#39;re going to place this role On Hold for now. <br> When you&#39;re ready to move forward, just reach out and we&#39;ll be happy to restart the process and coordinate scheduling. <br> In the meantime, we&#39;ll pause outreach on our end. <br> Best,',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'sri_sub_item5a',
                'html' => 'Change <strong>Job Status</strong> to <strong>On Hold</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'sri_sub_item5b',
                'html' => 'Post an update in #operations on Slack and hyperlink the client&#39;s name to the Job. Tag the recruiter in the Job',
                'value' => 'checked',
            ],
            [
                'kind' => 'note',
                'depth' => 2,
                'html' => ':ghost: Client Update: CLIENT Day 5. Still unresponsive re: second round. Called, texted, and emailed. Moved ticket to On Hold. @RECRUITER',
            ],
        ],
        'subtitle' => 'You&#39;re only required to submit one of these every day. However, make sure to follow these checklists as many times as necessary.',
        'form' => [
            'id' => 'checklist-form-10',
            'action' => 'https://hooks.zapier.com/hooks/catch/10540636/2gyrtr0',
            'target' => 'hidden_iframe_10',
            'checklist_name' => 'Daily Follow Up Checklist',
            'require_name' => false,
        ],
    ],
    [
        'kind' => 'heading',
        'text' => 'Situational (use as you&#39;re completing tasks)',
    ],
    [
        'kind' => 'checklist',
        'title' => 'Announcement Emojis',
        'blocks' => [
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Use the following emojis when making announcements in #operations on Slack',
            ],
        ],
    ],
    [
        'kind' => 'checklist',
        'title' => 'Personal Zoom Links',
        'blocks' => [
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Staff',
            ],
            [
                'kind' => 'note',
                'depth' => 0,
                'html' => '<ul> <li>Abbas &#45; <a href="https://www.remoteleverage.com/zoom" target="_blank" rel="noopener">https://www.remoteleverage.com/zoom</a></li> <li>Adam &#45; <a href="https://us02web.zoom.us/j/9582719834?pwd=qoFTykFGilXzCmP38O6fxy6FiybJHh.1" target="_blank" rel="noopener">https://us02web.zoom.us/j/9582719834?pwd=qoFTykFGilXzCmP38O6fxy6FiybJHh.1</a></li> <li>Andre &#45; <a href="https://us02web.zoom.us/j/9538994850?pwd=WeYjoKcQT6YF1gMdYpulXy9HuVOgNK.1" target="_blank" rel="noopener">https://us02web.zoom.us/j/9538994850?pwd=WeYjoKcQT6YF1gMdYpulXy9HuVOgNK.1</a></li> <li>Nock &#45; <a href="https://us02web.zoom.us/j/6395273555?pwd=UUUIXYqLd3GKDDvFIaowzEUY3apfCk.1" target="_blank" rel="noopener">https://us02web.zoom.us/j/6395273555?pwd=UUUIXYqLd3GKDDvFIaowzEUY3apfCk.1</a></li> <li>Torin &#45; <a href="https://us02web.zoom.us/j/2180178847?pwd=MVmZhStG45RmvbARqGt2PUkXFAtN5S.1" target="_blank" rel="noopener">https://us02web.zoom.us/j/2180178847?pwd=MVmZhStG45RmvbARqGt2PUkXFAtN5S.1</a></li> </ul>',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Account Executives',
            ],
            [
                'kind' => 'note',
                'depth' => 0,
                'html' => '<ul> <li>David &#45; <a href="https://us02web.zoom.us/j/9961668221?pwd=ffStMZWWX1qkhZ0Q35AGkYTyKa6CRy.1" target="_blank" rel="noopener">https://us02web.zoom.us/j/9961668221?pwd=ffStMZWWX1qkhZ0Q35AGkYTyKa6CRy.1</a></li> <li>Miguel &#45; <a href="https://us02web.zoom.us/j/7202798632?pwd=Oo5iIc57vzbbV0vglRgTRAt6GQ1qt1.1" target="_blank" rel="noopener">https://us02web.zoom.us/j/7202798632?pwd=Oo5iIc57vzbbV0vglRgTRAt6GQ1qt1.1</a></li> <li>Ivan M &#45; <a href="https://us02web.zoom.us/j/9596766246?pwd=VD3yfyRAoP8vAXcugICxrrd66pGQHw.1" target="_blank" rel="noopener">https://us02web.zoom.us/j/9596766246?pwd=VD3yfyRAoP8vAXcugICxrrd66pGQHw.1</a></li> <li>Princess &#45; <a href="https://us02web.zoom.us/j/7420870632?pwd=GHRCcC1JFvv69k4f6Z7Rha9SF9ihvY.1" target="_blank" rel="noopener">https://us02web.zoom.us/j/7420870632?pwd=GHRCcC1JFvv69k4f6Z7Rha9SF9ihvY.1</a></li> <li>Suhey &#45; <a href="https://us02web.zoom.us/j/5136668419?pwd=f2S3wkbSpGGc2iQ3n5srhCaTk5beJS.1" target="_blank" rel="noopener">https://us02web.zoom.us/j/5136668419?pwd=f2S3wkbSpGGc2iQ3n5srhCaTk5beJS.1</a></li> <li>Xavier &#45; <a href="https://us02web.zoom.us/j/7583897758?pwd=F5nHR2ohjR3vb0z7AClFDehUKybVwb.1" target="_blank" rel="noopener">https://us02web.zoom.us/j/7583897758?pwd=F5nHR2ohjR3vb0z7AClFDehUKybVwb.1</a></li> </ul>',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Hiring Managers',
            ],
            [
                'kind' => 'note',
                'depth' => 0,
                'html' => '<ul> <li>Andrea &#45; <a href="https://us02web.zoom.us/j/4133187639?pwd=s0wbaGlIB6S8so8JiSHpNTDsGatpEO.1" target="_blank" rel="noopener">https://us02web.zoom.us/j/4133187639?pwd=s0wbaGlIB6S8so8JiSHpNTDsGatpEO.1</a></li> <li>Anthony &#45; <a href="https://us02web.zoom.us/j/5999698011?pwd=BK4aoffoNji1tEEK0m3O1WzZbnZCW2.1" target="_blank" rel="noopener">https://us02web.zoom.us/j/5999698011?pwd=BK4aoffoNji1tEEK0m3O1WzZbnZCW2.1</a></li> <li>Bryan &#45; <a href="https://us02web.zoom.us/j/5511952792?pwd=HUaNfDPAr31MqkM8c9melhpGeFwZZ1.1" target="_blank" rel="noopener">https://us02web.zoom.us/j/5511952792?pwd=HUaNfDPAr31MqkM8c9melhpGeFwZZ1.1</a></li> <li>Caprice &#45; <a href="https://us02web.zoom.us/j/9763854021?pwd=G9oJvh6uHRSgx1OMDPf66NR1VbnpNh.1" target="_blank" rel="noopener">https://us02web.zoom.us/j/9763854021?pwd=G9oJvh6uHRSgx1OMDPf66NR1VbnpNh.1</a></li> <li>Carla &#45; <a href="https://us02web.zoom.us/j/2922720863?pwd=yXSqh6hXXeQbfcIkaMX9475wvK0yx9.1" target="_blank" rel="noopener">https://us02web.zoom.us/j/2922720863?pwd=yXSqh6hXXeQbfcIkaMX9475wvK0yx9.1</a></li> <li>Christy &#45; <a href="https://us02web.zoom.us/j/3637103608?pwd=b50uyY1B5Tt6SNQsEobIfhVGYjZiOv.1" target="_blank" rel="noopener">https://us02web.zoom.us/j/3637103608?pwd=b50uyY1B5Tt6SNQsEobIfhVGYjZiOv.1</a></li> <li>Dave &#45; <a href="https://us02web.zoom.us/j/9573425408?pwd=eERA3LKKozq5pfvOwMT66ekSdi5ySo.1" target="_blank" rel="noopener">https://us02web.zoom.us/j/9573425408?pwd=eERA3LKKozq5pfvOwMT66ekSdi5ySo.1</a></li> <li>Diego &#45; <a href="https://us02web.zoom.us/j/6591188189?pwd=lHUwElb3lc2bDzTUspNPDxbHHyDe2B.1" target="_blank" rel="noopener">https://us02web.zoom.us/j/6591188189?pwd=lHUwElb3lc2bDzTUspNPDxbHHyDe2B.1</a></li> <li>Gabriel &#45; <a href="https://us02web.zoom.us/j/3540409403?pwd=vSa20JXdttEUYtsv2twTrddEUzpBY7.1" target="_blank" rel="noopener">https://us02web.zoom.us/j/3540409403?pwd=vSa20JXdttEUYtsv2twTrddEUzpBY7.1</a></li> <li>Harry &#45; <a href="https://us02web.zoom.us/j/9465043538?pwd=7R1IQA1bvz7rpTWxONq3UaVyU3O0lv.1" target="_blank" rel="noopener">https://us02web.zoom.us/j/9465043538?pwd=7R1IQA1bvz7rpTWxONq3UaVyU3O0lv.1</a></li> <li>Ivan &#45; <a href="https://us02web.zoom.us/j/5140561947?pwd=fRoxxHTHqqBqbgaNmIKSEkrntLvqAw.1" target="_blank" rel="noopener">https://us02web.zoom.us/j/5140561947?pwd=fRoxxHTHqqBqbgaNmIKSEkrntLvqAw.1</a></li> <li>Jack &#45; <a href="https://us02web.zoom.us/j/2021359793?pwd=onvWOiiRcrat9SYSabkybAqeayhe2k.1" target="_blank" rel="noopener">https://us02web.zoom.us/j/2021359793?pwd=onvWOiiRcrat9SYSabkybAqeayhe2k.1</a></li> <li>Manny &#45; <a href="https://us02web.zoom.us/j/3469653070?pwd=eoxlYvWEPigQjrONIMD2ikBtckdl3B.1" target="_blank" rel="noopener">https://us02web.zoom.us/j/3469653070?pwd=eoxlYvWEPigQjrONIMD2ikBtckdl3B.1</a></li> <li>Mario &#45; <a href="https://us02web.zoom.us/j/7893158187?pwd=RdTPQJ4J6l6w2m8bf3jBEKWO1oDfwR.1" target="_blank" rel="noopener">https://us02web.zoom.us/j/7893158187?pwd=RdTPQJ4J6l6w2m8bf3jBEKWO1oDfwR.1</a></li> <li>Matt &#45; <a href="https://us02web.zoom.us/j/9755451690?pwd=0BxIOlEo0IAkf5zvUfJ9QF6KkRupRX.1" target="_blank" rel="noopener">https://us02web.zoom.us/j/9755451690?pwd=0BxIOlEo0IAkf5zvUfJ9QF6KkRupRX.1</a></li> <li>Melania &#45; <a href="https://us02web.zoom.us/j/3642723984?pwd=ra7VQJYFEPfJxPEqNr6zMzOrD8qJZ7.1" target="_blank" rel="noopener">https://us02web.zoom.us/j/3642723984?pwd=ra7VQJYFEPfJxPEqNr6zMzOrD8qJZ7.1</a></li> <li>Natasha &#45; <a href="https://us02web.zoom.us/j/9332456045?pwd=8vQ6hCPwyVX6Fu4ntKuLrXy3NQeQwP.1" target="_blank" rel="noopener">https://us02web.zoom.us/j/9332456045?pwd=8vQ6hCPwyVX6Fu4ntKuLrXy3NQeQwP.1</a></li> <li>Sam &#45; <a href="https://us02web.zoom.us/j/6695617034?pwd=WkgyunTTmUQlwlrv8fdGaE7y2Rd8AT.1" target="_blank" rel="noopener">https://us02web.zoom.us/j/6695617034?pwd=WkgyunTTmUQlwlrv8fdGaE7y2Rd8AT.1</a></li> <li>Sebastian &#45; <a href="https://us02web.zoom.us/j/6034854333?pwd=fQR3qcIMvseDZ6usH1LEkc0WSAEE0s.1" target="_blank" rel="noopener">https://us02web.zoom.us/j/6034854333?pwd=fQR3qcIMvseDZ6usH1LEkc0WSAEE0s.1</a></li> <li>Skylar &#45; <a href="https://us02web.zoom.us/j/9745525234?pwd=kJtBvOi7dbI0gagPWYXbISDnMf7tvq.1" target="_blank" rel="noopener">https://us02web.zoom.us/j/9745525234?pwd=kJtBvOi7dbI0gagPWYXbISDnMf7tvq.1</a></li> </ul>',
            ],
        ],
    ],
    [
        'kind' => 'checklist',
        'title' => 'New Client Assigned',
        'blocks' => [
            [
                'kind' => 'text',
                'name' => 'hiringManagerName',
                'label' => 'Hiring Manager Name:',
                'placeholder' => 'Enter name...',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Ticket created automatically',
                'emoji' => '►',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Double-check everything',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'nc_item1',
                'html' => 'In the CRM searchbar, type in the client&#39;s name. You&#39;ll find the Job under <strong>Jobs</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'nc_item1b',
                'html' => 'Double check all of the following',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'nc_item1c',
                'html' => 'Make sure you&#39;re the owner of the Job -&gt; your name should be next to the profile <svg><circle></circle><circle></circle><path></path></svg> icon',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'nc_item1d',
                'html' => 'Notes are added',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 3,
                'name' => 'nc_item1e',
                'html' => 'Both <strong>LIVE UPDATES</strong> and <strong>Candidate Vetting Updates</strong> notes are added -&gt; Pin them both',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 3,
                'name' => 'nc_item1f',
                'html' => '<strong>REFERENCE THIS INFORMATION BEFORE AND DURING ONBOARDING CALL</strong> is added -&gt; Do NOT pin it',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'nc_item1g',
                'html' => 'Name of the Salesperson is added',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'nc_item1h',
                'html' => 'Add <strong>Onboarding Date &#45; Time</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Update Job Status',
            ],
            [
                'kind' => 'item',
                'depth' => 0,
                'name' => 'nc_item7',
                'html' => 'Change it to the correct stage. In the dropdown menu, select either <strong>Onboarding Booked</strong> or <strong>Onboarding NOT Booked</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Post Slack update',
            ],
            [
                'kind' => 'item',
                'depth' => 0,
                'name' => 'nc_item9',
                'html' => 'Copy and paste the following as a reply to the automated message in the #new-clients-payments that notified you of the new client (Stripe message):',
                'value' => 'checked',
            ],
            [
                'kind' => 'note',
                'depth' => 0,
                'html' => '* New client: CLIENTNAME<br> * Job auto-created and verified<br> * Intro email and SMS auto-sent and verified<br> * Onboarding Booked / Onboarding NOT Booked (DATE)',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Create ticket manually',
                'emoji' => '►',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'nc_manual1',
                'html' => 'Open the client&#39;s contact in the CRM (it&#39;s the one with the red icon)',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'nc_manual2',
                'html' => 'Under the <strong>Jobs</strong> tab, click on <strong>Add job</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'nc_manual3',
                'html' => 'Fill out the following fields:',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'nc_manual3a',
                'html' => 'Job Title*',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'nc_manual3b',
                'html' => 'Salesperson',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'nc_manual3c',
                'html' => 'Onboarding Date &#45; Time',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'nc_manual4',
                'html' => 'UNCHECK <strong>Enable Job Application Form</strong> box',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'nc_manual5',
                'html' => 'Click <strong>Save Job</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Preparation',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'nc_item_final',
                'tone' => 'success',
                'html' => 'Read the notes from the sales team on the contact card of the client to understand what the client is looking for prior to your onboarding meeting',
                'value' => 'checked',
            ],
        ],
        'form' => [
            'id' => 'checklist-form-2001',
            'action' => 'https://hooks.zapier.com/hooks/catch/10540636/2gyrtr0',
            'target' => 'hidden_iframe_2001',
            'checklist_name' => 'New Client Assigned Checklist',
            'require_name' => true,
        ],
    ],
    [
        'kind' => 'checklist',
        'title' => 'Client Canceled',
        'blocks' => [
            [
                'kind' => 'text',
                'name' => 'hiringManagerName',
                'label' => 'Hiring Manager Name:',
                'placeholder' => 'Enter name...',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Document',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'cc_doc1',
                'html' => 'In the Job, leave a note with information on what happened in <strong>LIVE UPDATES</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'note',
                'depth' => 1,
                'html' => '12/26 VI &#45; Client canceled and requested a refund; says they&#39;re not comfortable paying 40% of annual salary for a PT role',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'cc_doc2',
                'html' => 'Drop a message in #operations chat on Slack to inform the team <em>hyperlink the client&#39;s name to the Job</em>',
                'value' => 'checked',
            ],
            [
                'kind' => 'note',
                'depth' => 1,
                'html' => ':poop: Client Update Dixie Normous<br> Client canceled and requested a refund; says they&#39;re not comfortable paying 40% of annual salary for a PT role',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'cc_doc3',
                'html' => 'Update the <strong>Candidate Vetting Updates</strong> note',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'cc_doc3a',
                'html' => 'Remove the VAs under &quot;Added&quot; and add them under &quot;Removed&quot; <em>i.e. 12/26 VI Aitor Tilla (client canceled)</em>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'cc_doc3b',
                'html' => 'Remove all VAs from the ticket',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'cc_doc4',
                'html' => 'Update the Job Status to <strong>On Hold</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'cc_doc4a',
                'html' => 'Only after the client has been refunded (you&#39;ll find confirmation in the #refund channel on Slack)&#46;&#46;&#46;',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 3,
                'name' => 'cc_doc4a1',
                'html' => 'Update the <strong>Job Status</strong> to <strong>Canceled</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 3,
                'name' => 'cc_doc4a1a',
                'html' => 'If the client canceled <u>before</u> the onboarding, update the <strong>Job Status</strong> to <strong>Canceled &#45; Before Onboarding</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 3,
                'name' => 'cc_doc4a2',
                'html' => 'Navigate to the <strong>Related Deal</strong> tab -&gt; click on <strong>won</strong>, in the green field -&gt; change <strong>Deal Stage</strong> to <strong>Canceled (after signing up)</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Send Refund Request Form',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'cc_item1',
                'html' => 'Send the client an email via Gmail',
                'value' => 'checked',
            ],
            [
                'kind' => 'template',
                'id' => 'email-content-20',
                'html' => '<strong>Subject:</strong> Refund Request – Next Steps <hr> <p>Hi client,</p> <p>In order for us to process your refund of the deposit, we&#39;ll need you to complete a quick Refund Request Form. This helps us verify the details and ensure the refund is issued to the correct account.</p> <p>Please use the link below to fill out the form:<br> <a href="https://form.jotform.com/252185178652665" target="_blank" rel="noopener">Deposit Refund Request Form</a></p> <p>Once submitted, we&#39;ll begin processing your refund. Please allow 7–10 business days for the funds to return to your account, depending on your bank or card issuer.</p> <p>Thank you for trying out Remote Leverage.</p> <p>Best,</p>',
            ],
        ],
        'form' => [
            'id' => 'checklist-form-20',
            'action' => 'https://hooks.zapier.com/hooks/catch/10540636/2gyrtr0',
            'target' => 'hidden_iframe_20',
            'checklist_name' => 'Client Canceled Checklist',
            'require_name' => true,
        ],
    ],
    [
        'kind' => 'checklist',
        'title' => 'Client No-Show',
        'blocks' => [
            [
                'kind' => 'text',
                'name' => 'hiringManagerName',
                'label' => 'Hiring Manager Name:',
                'placeholder' => 'Enter name...',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Onboarding call',
                'emoji' => '►',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ns_item1',
                'html' => 'Call the client 5 minutes in. If no response, leave a voicemail message',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ns_item2',
                'html' => 'In Google Calendar, click on the Onboarding Meeting and scroll all the way down to find the Calendly link under &quot;Reschedule:&quot;',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'ns_item2a',
                'html' => 'Copy the link',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'ns_item2b',
                'html' => 'Use this template to email the client 10 minutes in, and share the link for them to reschedule',
                'value' => 'checked',
            ],
            [
                'kind' => 'template',
                'id' => 'email-content-356',
                'html' => '<strong>Subject:</strong> Rescheduling your missed Onboarding meeting <hr> <p>Hi CLIENT,</p> <p>We were scheduled to start our onboarding call about 10 minutes ago, but I didn&#39;t see you join. I also tried calling you shortly after and wasn&#39;t able to reach you.</p> <p>If something came up, no problem. Please use the link below to reschedule at a time that works best for you:<br>LINK</p> <p>Once rescheduled, we&#39;ll be happy to move things forward.</p> <p>Best,</p>',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ns_item3',
                'html' => 'Open the Job',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'ns_item3a',
                'html' => 'Change the Job Status to <strong>Missed Onboarding Meeting</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 3,
                'name' => 'ns_item3a1',
                'html' => 'Click <strong>Submit</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'ns_item3b',
                'html' => 'Leave a note in <strong>Live Updates</strong> detailing what happened and <strong>Save</strong> it',
                'value' => 'checked',
            ],
            [
                'kind' => 'note',
                'depth' => 2,
                'html' => '12/18 VI &#45; Client no-showed to onboarding. Called 5 minutes in, but no response. Sent follow-up email with link to reschedule',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ns_item4',
                'html' => 'Post an update in #operations chat on Slack to inform the team about what happened <em>hyperlink the job to CLIENT (ROLE)</em>',
                'value' => 'checked',
            ],
            [
                'kind' => 'note',
                'depth' => 1,
                'html' => ':red_circle: Onboarding with CLIENT (ROLE)<br>Client no-showed. Called 5 minutes in, but no response. Sent follow-up email with link to reschedule',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ns_item5',
                'html' => 'Post an update in #onboarding-attendance chat on Slack. Use the following format:',
                'value' => 'checked',
            ],
            [
                'kind' => 'note',
                'depth' => 1,
                'html' => '@salesperson<br>link to Contact page (not Job)<br>RELEVANT NOTES',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'VA interviews',
                'emoji' => '►',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'va_item1',
                'html' => 'Call the client 5 minutes in. If no response, leave a voicemail message',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'va_item1a',
                'html' => 'Call a second time 10 minutes in. If no response, leave another voicemail message and make sure to tell the client that you have candidates in the waiting room',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 3,
                'name' => 'va_item1a1',
                'html' => 'Send a SMS',
                'value' => 'checked',
            ],
            [
                'kind' => 'note',
                'depth' => 3,
                'html' => 'Hi CLIENT. We have interviews scheduled right now. Please join us: <em>post Zoom link here</em>',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'va_item1b',
                'html' => 'Call a third time 15 minutes in. If no response, leave yet another voicemail message',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'va_item2',
                'html' => 'Bring all VAs in from the Waiting Room',
                'value' => 'checked',
            ],
            [
                'kind' => 'note',
                'depth' => 1,
                'html' => '&quot;Unfortunately, the client has had something come up at the last minute, and won&#39;t be able to join us. Please keep an eye on your inbox, as we will send you an updated invitation once a new date and time has been set&quot;',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'va_item3',
                'html' => 'In Google Calendar, click on the Interview and scroll all the way down to find the Calendly link under &quot;Reschedule:&quot;',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'va_item3a',
                'html' => 'Copy the link',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'va_item3b',
                'html' => 'Use this template to email the client 15 minutes in, and share the link for them to reschedule',
                'value' => 'checked',
            ],
            [
                'kind' => 'template',
                'id' => 'email-content-va',
                'html' => '<strong>Subject:</strong> Rescheduling your missed VA Interview <hr> <p>Hi CLIENT,</p> <p>We were scheduled to start our VA interviews about 15 minutes ago, but I didn&#39;t see you join. I also tried calling you shortly after and wasn&#39;t able to reach you.</p> <p>If something came up, no problem. Please use the link below to reschedule at a time that works best for you.<br>LINK</p> <p>Once rescheduled, we&#39;ll be happy to move things forward.</p> <p>Best,</p>',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'va_item4',
                'html' => 'Go to the Job',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'va_item4a',
                'html' => 'Change Job Status to <strong>Client Missed VA Interview</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 3,
                'name' => 'va_item4a1',
                'html' => 'Click <strong>Submit</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'va_item4b',
                'html' => 'Leave a note in <strong>Live Updates</strong> detailing what happened and <strong>Save</strong> it',
                'value' => 'checked',
            ],
            [
                'kind' => 'note',
                'depth' => 2,
                'html' => '02/07 VI &#45; Client no-showed to VA interviews. Called 5 minutes in, but no response. Sent follow-up email with link to reschedule',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'va_item5',
                'html' => 'Post an update in #operations chat on Slack to inform the team about what happened <em>hyperlink the job to CLIENT (ROLE)</em>',
                'value' => 'checked',
            ],
            [
                'kind' => 'note',
                'depth' => 1,
                'html' => ':red_circle: Interviews with CLIENT (ROLE)<br>Client no-showed. Called 5, 10, and 15 minutes in, but no response. Sent follow-up email with link to reschedule',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Follow-up meeting',
                'emoji' => '►',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'fu_item1',
                'html' => 'Call the client 5 minutes in. If no response, leave a voicemail message and send a SMS',
                'value' => 'checked',
            ],
            [
                'kind' => 'note',
                'depth' => 1,
                'html' => 'Hi CLIENT. We have a follow-up meeting scheduled right now. Please join me: <em>post Zoom link here</em>',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'fu_item2',
                'html' => 'If 10 minutes in, the client still doesn&#39;t show&#46;&#46;&#46;',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'fu_item2a',
                'html' => 'Go to the Job',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'fu_item2b',
                'html' => 'Change Job Status to <strong>On Hold &#45; Follow Up</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'fu_item2c',
                'html' => 'Leave a note in <strong>Live Updates</strong> detailing what happened and <strong>Save</strong> it',
                'value' => 'checked',
            ],
            [
                'kind' => 'note',
                'depth' => 2,
                'html' => '02/07 VI &#45; Client no-showed follow-up meeting. Called 5 minutes in, but no response. Sent follow-up SMS and email with link to reschedule',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'fu_item3',
                'html' => 'Post an update in #operations chat on Slack to inform the team about what happened <em>hyperlink the job to CLIENT (ROLE)</em>',
                'value' => 'checked',
            ],
            [
                'kind' => 'note',
                'depth' => 1,
                'html' => ':red_circle: Follow-up meeting with CLIENT (ROLE)<br>Client no-showed. Called 5 minutes in, but no response. Sent follow-up SMS and email with link to reschedule. Placed ticket OH-FU',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'fu_item4',
                'html' => 'Set a reminder on Slack in any channel',
                'value' => 'checked',
            ],
            [
                'kind' => 'note',
                'depth' => 1,
                'html' => '/remind @me to follow-up with CLIENT tomorrow',
            ],
        ],
        'form' => [
            'id' => 'checklist-form-356',
            'action' => 'https://hooks.zapier.com/hooks/catch/10540636/2gyrtr0',
            'target' => 'hidden_iframe_356',
            'checklist_name' => 'Client No-Show Checklist',
            'require_name' => true,
        ],
    ],
    [
        'kind' => 'checklist',
        'title' => 'Client Onboarding Meeting',
        'blocks' => [
            [
                'kind' => 'text',
                'name' => 'hiringManagerName',
                'label' => 'Hiring Manager Name:',
                'placeholder' => 'Enter name...',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Pre-Meeting',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ci_item1',
                'html' => 'Open <a href="https://submit.jotform.com/242937701106049" target="_blank" rel="noopener">Client Onboarding Form</a>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ci_item2',
                'html' => 'Open the Job ticket in the CRM',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'During meeting',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ci_item3',
                'html' => 'Follow slides exactly as written – no deviations from script',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ci_item4',
                'html' => 'Verify whether the following information is correct for direct contact with the client',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'ci_item4a',
                'html' => 'email address',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'ci_item4b',
                'html' => 'cell phone number',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ci_item5',
                'html' => 'Note additional stakeholders to include in future emails/interviews. Get first and last name, and an email to contact them directly',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Schedule Top VA Presentation',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ci_tvp0',
                'html' => 'Schedule using your <strong>Interview Results &amp; Top Candidate Presentation</strong> Calendly',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ci_tvp1',
                'html' => 'Go to the <a href="https://recruiting-helper.replit.app/interviews" target="_blank" rel="noopener">Recruiter Helper</a> tool',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ci_tvp2',
                'html' => 'Click on <strong>2nd Round</strong> at the top of the screen',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ci_tvp3',
                'html' => 'Under <strong>All Hiring Manager</strong> dropdown menu, select your name',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ci_tvp4',
                'html' => 'Find the corresponding event',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ci_tvp5',
                'html' => 'Click <strong>+ Create Event</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'ci_tvp5a',
                'html' => 'Enter Role and change recruiter email to yours',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ci_tvp6',
                'html' => 'Double-check everything and click <strong>Create Event</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Schedule VA interviews',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ci_item11',
                'html' => 'Schedule immediately if hiring within 2 weeks',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'ci_item12',
                'html' => 'Book using your Calendly (YourName: Virtual Assistant Interview)',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'ci_item13',
                'html' => 'If booked, add the role in parenthesis <em>i.e. Zoe Leotard (Admin Assistant)</em>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 3,
                'name' => 'ci_item13_bundle',
                'html' => 'If it&#39;s a Bundle, add the number of the Bundle (3, 5, 7, 10), followed by B. Add /1, /2, /3, etc to indicate which Bundle hire this role is for <em>i.e. (3B/1) Zoe Leotard (Admin Assistant)</em>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 3,
                'name' => 'ci_item13_confirm',
                'html' => 'Have the client confirm attendance during the call <em>&quot;Could you check your inbox to see if you received the invite? Great! Please confirm attendance. I want to make sure you don&#39;t miss it.&quot;</em>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'ci_item13a',
                'html' => 'Add a message in the #operations chat on Slack to inform the team on how the onboarding went. Copy and paste the following and add your own notes:',
                'value' => 'checked',
            ],
            [
                'kind' => 'note',
                'depth' => 2,
                'html' => ':large_green_circle: Finished onboarding with CLIENT <em>include whether financing was discussed, whether it&#39;s an individual hire or a Bundle, and any other relevant info</em>',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ci_item14',
                'html' => 'Update Job Status to <strong>Hiring Form Submitted</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ci_item14b',
                'html' => 'Fill in Interview Date &#45; Time field in the ticket',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ci_item14c',
                'html' => 'Write any relevant information in the <strong>Live Updates</strong> note and <strong>Save</strong> it <em>i.e. if financing was discussed, what specific conditions, whether the client requested resumes before the interview, etc</em>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ci_item14d',
                'html' => 'Refresh the page to see the <strong>ONBOARDING FORM NOTES</strong>, and pin it',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'If not hiring immediately&#46;&#46;&#46;',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ci_item16',
                'html' => 'In the Job title, add the date the client wants to hire <em>i.e. 05/21/26 Zoe Leotard (Admin Assistant)</em>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ci_item16_reminder',
                'html' => 'In any channel on Slack, type in the following to instruct Slack to ping you and remind you when to reach out to the client. Make sure that you set the reminder date to 8 days before.',
                'value' => 'checked',
            ],
            [
                'kind' => 'note',
                'depth' => 1,
                'html' => '/remind @YourName on May 13 to Follow up with CLIENT to schedule interviews for the following week',
            ],
            [
                'kind' => 'note',
                'depth' => 1,
                'tone' => 'warning',
                'html' => 'Once the reminder has been set, you&#39;ll be informed by Slack whether it was created successfully. It&#39;ll look like this: Slackbot I will remind you to &quot;Follow up with CLIENT to schedule interviews for the following week&quot; on May 13 at 9:00 AM.',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ci_item16a',
                'html' => 'Change Job Status to <strong>On Hold &#45; Follow Up</strong>',
                'value' => 'checked',
            ],
        ],
        'form' => [
            'id' => 'checklist-form-3',
            'action' => 'https://hooks.zapier.com/hooks/catch/10540636/2gyrtr0',
            'target' => 'hidden_iframe_3',
            'checklist_name' => 'Client Onboarding Meeting Checklist',
            'require_name' => true,
        ],
    ],
    [
        'kind' => 'checklist',
        'title' => 'VA Interviews',
        'blocks' => [
            [
                'kind' => 'text',
                'name' => 'hiringManagerName',
                'label' => 'Hiring Manager Name:',
                'placeholder' => 'Enter name...',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Pre-interview setup',
                'emoji' => '►',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'cli_item1',
                'html' => 'Ensure candidates are scheduled 15 minutes early (critical for testing audio/video/background and for presentation)',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'cli_item2',
                'html' => 'If fewer than 3 candidates show up, submit a Firefight Support Request form (<a href="https://submit.jotform.com/252558126272155" target="_blank" rel="noopener">here</a>)',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'cli_item2_confirm',
                'html' => 'Confirm at least 3 qualified candidates are present before starting',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'cli_item4',
                'html' => 'Brief the candidates using the <a href="https://www.canva.com/design/DAGiFo7_qsw/yauDrc3uywex0tzcIrymjQ/edit" target="_blank" rel="noopener">Interview Process v.2</a> presentation',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'cli_item_new',
                'html' => 'Ensure that the VAs names on Zoom are set to first name only, their backgrounds are blurred, and they are well-presented to meet the client',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'cli_item2b',
                'html' => 'Once there are at least 3 candidates in the room, take a screenshot of the Participants with their respective cameras on, and post it in the #operations chat on Slack',
                'value' => 'checked',
            ],
            [
                'kind' => 'note',
                'depth' => 1,
                'html' => 'Starting interviews for CLIENT (ROLE) SCREENSHOT',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'cli_item_firefighting',
                'html' => 'VAs joining through firefighting? Bring them in after you&#39;ve interviewed the ones who joined from the beginning, do the presentation to get them ready for the interview',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'During and after interviews',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'cli_item5',
                'html' => 'Keep detailed notes of the interviews. Use the following format: <em>change italics for your own notes</em>',
                'value' => 'checked',
            ],
            [
                'kind' => 'template',
                'id' => 'notes-template-box',
                'html' => 'Candidate: NAME | AVAILABILITY Salary Exp: $ Internal Rating: /10 • Strengths: <em>yournotes</em> • Weaknesses: <em>yournotes</em>',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'cli_item5b',
                'html' => 'After the interviews, post interview notes in #operations chat on Slack',
                'value' => 'checked',
            ],
            [
                'kind' => 'template',
                'id' => 'slack-template-box',
                'html' => ':speaking_head_in_silhouette: Interviews for Ines Ezario (Appt Setter) Candidate: Aitor | immediately Salary Exp: $10 Internal Rating: 7/10 • Strengths: experienced, charismatic • Weaknesses: choppy English, responses are too short to be able to make a positive assessment Candidate: Cindy | 2 weeks Salary Exp: $8-$11 Internal Rating: 9/10 • Strengths: tons of experience, articulate, bubbly • Weaknesses: None Candidate: Alan | immediately Salary Exp: $12 Internal Rating: 6/10 • Strengths: has experience using SOHO • Weaknesses: boring, low energy',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'cli_item5c',
                'html' => 'Update <strong>Live Updates</strong> notes in the ticket with the interview notes',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Interview process',
                'emoji' => '►',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'cli_ip_1',
                'html' => 'Use the Interview Questions Generator (<a href="https://remoteleverage.com/tools/" target="_blank" rel="noopener">here</a>)',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'cli_ip_1a',
                'html' => 'Make sure to ask structured, role-specific questions',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'cli_ip_1b',
                'html' => 'Assess communication, confidence, culture fit, technical ability, and salary alignment',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'cli_ip_1c',
                'html' => 'Take detailed, client-ready notes',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'cli_ip_1d',
                'html' => 'Evaluate and rank candidates objectively',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Prepare VA Presentation (second-round equivalent)',
                'emoji' => '►',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'cli_post_1',
                'html' => 'Identify the top candidate (or two if justified)',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'cli_post_2',
                'html' => 'Ensure that the top candidate&#46;&#46;&#46;',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'cli_post_2a',
                'html' => 'meets salary expectations and availability requirements',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'cli_post_2b',
                'html' => 'is client-presentable',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'cli_post_3',
                'html' => 'Change <strong>Job Status</strong> to <strong>VA Presentation Scheduled</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'cli_post_4',
                'html' => 'Trim recording of top choice(s) to send to the client',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'cli_post_4a',
                'html' => 'Go to your <a href="https://us02web.zoom.us/recording?ampDeviceId=a17ed9f3-3804-4f28-92d6-8e55dba28167&amp;ampSessionId=1771512441686" target="_blank" rel="noopener">Recordings &amp; Transcripts</a>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'cli_post_4b',
                'html' => 'Click on the recording -&gt; <strong>Add to Zoom Clips</strong> -&gt; <strong>Trim video</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'cli_post_4c',
                'html' => 'After trimming, click <strong>Save</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'cli_post_4d',
                'html' => 'Go to <a href="https://us02web.zoom.us/clips" target="_blank" rel="noopener">Clips</a>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'cli_post_4e',
                'html' => 'Click on the 🔗 icon below the corresponding clip',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'cli_post_5',
                'html' => 'Email the client to present your top candidate . Make sure to attach the candidate&#39;s resume to the email. Also, change the &quot;here&quot; hyperlink to the clip',
                'value' => 'checked',
            ],
            [
                'kind' => 'template',
                'id' => 'email-template-post5',
                'html' => '<strong>Subject:</strong> [Remote Leverage] Top Candidate Recommendation <hr> <p>Hi CLIENT,</p> <p>As discussed, I&#39;m sharing the top candidate I&#39;m recommending for the NAMEOFROLE role. HIS/HER name is NAME. YOUR NOTES ABOUT THE CANDIDATE HERE</p> <p>You&#39;ll find the candidate&#39;s resume attached to this email. Also, you&#39;ll find the recording of the interview I conducted <a href="https://remoteleverage.com/tools/" target="_blank" rel="noopener">here</a>.</p> <p>Based on everything we&#39;ve discussed around the role, this candidate is the strongest fit and is fully ready to move forward.</p> <p>We&#39;re scheduled for you to meet HIM/HER on DATE at TIME. I recommend reviewing the materials ahead of time so we can use that time to confirm alignment and move straight into next steps.</p> <p>Looking forward to it!</p> <p>Best,</p>',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'cli_prep_1',
                'html' => 'Book the selected candidate 5 minutes before the Interview results &amp; Top Candidate Presentation event',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'cli_prep_1a',
                'html' => 'Go to your Google Calendar',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'VA presentation and close',
                'emoji' => '►',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'cli_close_1',
                'html' => 'Join the follow-up call. See if the client has any questions/comments before bringing in the VA',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'cli_close_2',
                'html' => 'Let the VA in and introduce them to the client',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'cli_close_3',
                'html' => 'Allow the client and the VA to speak directly',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'cli_close_4',
                'html' => 'After the discussion&#46;&#46;&#46;',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'cli_close_4a',
                'html' => 'assume the close',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'cli_close_4b',
                'html' => 'collect work offer details',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'cli_close_4c',
                'html' => 'send the Hiring Agreement during the call (follow the <strong>Send Hiring Agreement</strong> checklist)',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'cli_close_4d',
                'html' => 'schedule the Virtual Assistant &lt;&gt; Client Onboarding meeting using your Calendly (do not add the VA to the invite yet)',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'cli_close_5',
                'html' => 'Update the <strong>Job Status</strong> to <strong>Interviews Completed: Awaiting Decision</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'cli_close_6',
                'html' => 'Follow the <strong>Work Offer</strong> checklist',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Update failed candidates',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'cli_item18',
                'html' => 'Inform the VAs who failed the interviews. Use Gmail to send them the following email or use the <strong>Candidate Management Tool</strong> <a href="https://remoteleverage.com/tools/" target="_blank" rel="noopener">here</a>',
                'value' => 'checked',
            ],
            [
                'kind' => 'template',
                'id' => 'email-template-18',
                'html' => '<strong>Subject:</strong> Update on Client Interview &#45; Remote Leverage <hr> <p>Hi VANAME,</p> <p>Thank you again for taking the time to interview with CLIENT. After careful consideration, they&#39;ve decided to move forward with another candidate for this particular role.</p> <p>That said, we truly appreciate your professionalism and the value you bring. Rest assured that we&#39;ll keep you in mind for upcoming opportunities, and you can expect to receive invitations to future client jobs via email and calendar notifications.</p> <p>Thanks again, and we look forward to connecting you with the right opportunity soon.</p> <p>Best,</p>',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'cli_item19',
                'html' => 'Remove the non-selected VAs from the Job',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'cli_item19b',
                'html' => 'Update the Candidate Vetting Updates note and remove the VAs under &quot;Added&quot; and &quot;Approved by HM&quot;, and add them under &quot;Removed&quot; <em>i.e. 12/15 VI Aitor Tilla (failed)</em>',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Client isn&#39;t ready to hire after presentation (if applicable)',
                'emoji' => '►',
            ],
            [
                'kind' => 'note',
                'depth' => 1,
                'html' => 'Top choice not a fit',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'cli_notready_1',
                'html' => 'Use your Calendly to book a new <strong>Interview Results &amp; Top Candidate Presentation</strong> meeting',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 3,
                'name' => 'cli_notready_1a',
                'html' => 'Inform the client we will have a new top candidate ready in 5 business days (unless it&#39;s a difficult role to fill, in which case it&#39;ll be 7 business days)',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'cli_notready_2',
                'html' => 'Update the <strong>Job Status</strong> to <strong>Hiring Form Submitted</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 3,
                'name' => 'cli_notready_2a',
                'html' => 'Inform the team in #operations chat on Slack',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 3,
                'name' => 'cli_notready_2b',
                'html' => 'Update <strong>Live Notes</strong> in the Job',
                'value' => 'checked',
            ],
            [
                'kind' => 'note',
                'depth' => 1,
                'html' => 'Client needs time to make a decision',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'cli_notready_3',
                'html' => 'Reinforce that VAs cannot be held without a signed agreement and if a WO isn&#39;t sent out',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 3,
                'name' => 'cli_notready_3a',
                'html' => 'Book a meeting using your Remote Leverage &#45; Follow Up Call Calendly for the same day or the following morning',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 3,
                'name' => 'cli_notready_3b',
                'html' => 'Update the Job Status to <strong>Interviews Completed: Awaiting Decision</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'VA missed interview (if applicable)',
                'emoji' => '►',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'cli_vami_1',
                'html' => 'Follow the VA No-Showed to Interview checklist',
                'value' => 'checked',
            ],
        ],
        'form' => [
            'id' => 'checklist-form-18',
            'action' => 'https://hooks.zapier.com/hooks/catch/10540636/2gyrtr0',
            'target' => 'hidden_iframe_18',
            'checklist_name' => 'VA Interviews Checklist',
            'require_name' => true,
        ],
    ],
    [
        'kind' => 'checklist',
        'title' => 'VA No-Showed to Interview',
        'blocks' => [
            [
                'kind' => 'text',
                'name' => 'hiringManagerName',
                'label' => 'Hiring Manager Name:',
                'placeholder' => 'Enter name...',
            ],
            [
                'kind' => 'note',
                'depth' => 0,
                'html' => 'When the VA confirms attendance but doesn&#39;t show up. If the VA did not confirm attendance, it wouldn&#39;t be considered a no-show, but &quot;unresponsive&quot;, in which case, you don&#39;t need to follow this checklist.',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'vans_item1',
                'html' => 'Open the VA&#39;s profile',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'vans_item2',
                'html' => 'On the right-hand side of the profile, up top, click on the pencil (Edit) icon, next to the red flame icon',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'vans_item3',
                'html' => 'Scroll down to the field named <strong>Missed Interview Warning Email Sent</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'vans_item4',
                'html' => 'In the dropdown menu, select <strong>Warning Email 1</strong>; if already on 1, select <strong>Warning Email 2</strong>; if already on 2, select <strong>Warning Email 3</strong> -&gt; Click <strong>Submit</strong> at the bottom',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'vans_item5',
                'html' => 'In the VA&#39;s profile, click on their email to open the CRM&#39;s &quot;Compose Email&quot; -&gt; in the <strong>Email Templates</strong> dropdown, select the corresponding template <em>(VA Missed Client Interview: Warning Email 1, VA Missed Client Interview: Warning Email 2, or VA Missed Client Interview: Warning Email 3)</em>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'vans_item6',
                'html' => 'Click <strong>Send</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'vans_item7',
                'html' => 'Create a new note -&gt; Insert the <strong>Candidate Attendance Record</strong> template',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'vans_item8',
                'html' => 'Below <strong>No-Show</strong> section, add MM/DD Initials ClientName',
                'value' => 'checked',
            ],
            [
                'kind' => 'note',
                'depth' => 2,
                'html' => '12/12 VI Ben Kruppt',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'vans_item9',
                'html' => 'Click <strong>+ Add Note</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'vans_item10',
                'html' => 'If <em>VA Missed Client Interview: Warning Email 3</em> was already sent, and the VA missed an interview with you&#46;&#46;&#46;',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'vans_item11',
                'html' => 'Change the Candidate Stage to <strong>Disqualified: Check Notes</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'vans_item12',
                'html' => 'Navigate to the <strong>Assigned Jobs</strong> tab and remove the VA from ALL Jobs',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'vans_item13',
                'html' => 'Inform the team about what happened in #operations chat on Slack; hyperlink the VA&#39;s name to the VA&#39;s profile',
                'value' => 'checked',
            ],
            [
                'kind' => 'note',
                'depth' => 2,
                'html' => ':warning: VANAME no-showed to interviews more than 3 times. Blacklisted',
            ],
        ],
        'form' => [
            'id' => 'checklist-form-19',
            'action' => 'https://hooks.zapier.com/hooks/catch/10540636/2gyrtr0',
            'target' => 'hidden_iframe_19',
            'checklist_name' => 'VA No-Showed to Interview Checklist',
            'require_name' => true,
        ],
    ],
    [
        'kind' => 'checklist',
        'title' => 'Client Rescheduled VA Interview',
        'blocks' => [
            [
                'kind' => 'text',
                'name' => 'hiringManagerName',
                'label' => 'Hiring Manager Name:',
                'placeholder' => 'Enter name...',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Before the actual meeting',
                'emoji' => '►',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'cr_item0',
                'html' => 'In Google Calendar, click on the VA invitation -&gt; click on the trash 🗑️ icon -&gt; copy and paste the following message',
                'value' => 'checked',
            ],
            [
                'kind' => 'note',
                'depth' => 1,
                'html' => 'Something has come up for the client, so today&#39;s interview has been canceled. We will send an updated invitation with the new date and time as soon as the client is able to reschedule. Thank you for your understanding and flexibility.',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'cr_item0b',
                'html' => 'Once you have a new time and date from the client',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'cr_item0c',
                'html' => 'Go to <a href="https://www.calendly.com" target="_blank" rel="noopener">Calendly</a> and sign in',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 3,
                'name' => 'cr_item0d',
                'html' => 'Click <strong>Meetings</strong> tab on the left side -&gt; click on the meeting in question -&gt; click <strong>Reschedule</strong> -&gt; <strong>Choose team member</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 3,
                'name' => 'cr_item0e',
                'html' => 'Make sure you&#39;re selected and click <strong>Select a time</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 3,
                'name' => 'cr_item0f',
                'html' => 'Select the new date and time and enter the corresponding information',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 3,
                'name' => 'cr_item0f2',
                'html' => 'Add a reason under <strong>Reason for change</strong> -&gt; click <strong>Update event</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'cr_item0g',
                'html' => 'Post in #operations channel in Slack to inform your Senior Recruiter',
                'value' => 'checked',
            ],
            [
                'kind' => 'note',
                'depth' => 2,
                'html' => '@RECRUITERNAME CLIENTNAME client rescheduled interviews for MM/DD at TIME PST. Please send out new VA invites',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'During the actual meeting',
                'emoji' => '►',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'cr_item1a',
                'html' => 'Bring all VAs in from the Waiting Room',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 2,
                'title' => 'New date and time already set',
            ],
            [
                'kind' => 'note',
                'depth' => 2,
                'html' => '&quot;Unfortunately, the client has had something come up at the last minute, and won&#39;t be able to join us. He/She has rescheduled for [date] at [time]. Please keep an eye on your inbox; we will send you an updated invitation shortly&quot;',
            ],
            [
                'kind' => 'group',
                'depth' => 2,
                'title' => 'New date and time NOT set',
            ],
            [
                'kind' => 'note',
                'depth' => 2,
                'html' => '&quot;Unfortunately, the client has had something come up at the last minute, and won&#39;t be able to join us. Please keep an eye on your inbox, as we will send you an updated invitation once a new date and time has been set&quot;',
            ],
        ],
        'form' => [
            'id' => 'checklist-form-14',
            'action' => 'https://hooks.zapier.com/hooks/catch/10540636/2gyrtr0',
            'target' => 'hidden_iframe_14',
            'checklist_name' => 'Client Rescheduled VA Interview Checklist',
            'require_name' => true,
        ],
    ],
    [
        'kind' => 'checklist',
        'title' => '2nd-Round Interviews Setup',
        'blocks' => [
            [
                'kind' => 'text',
                'name' => 'hiringManagerName',
                'label' => 'Hiring Manager Name:',
                'placeholder' => 'Enter name...',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Schedule interviews',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'rti_item1',
                'html' => 'Book the second-round of interviews using your Calendly (YourName: 2nd Interview W/ Top Candidates)',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'rti_item2b',
                'html' => 'Update the Interview Date &#45; Time field in the Job to reflect the time and date of the second-round of interviews',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Create the Calendar Invite for the VA(s)',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'rti_item3',
                'html' => 'Create the second-round of interviews Calendar invite',
                'value' => 'checked',
            ],
            [
                'kind' => 'note',
                'depth' => 2,
                'html' => 'Calendar Setup:',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'rti_item4',
                'html' => 'Create a new invite in Google Calendar 5 minutes before the time the second-round of interviews is set to start',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'rti_item5',
                'html' => 'Name the event &quot;Second Round of Interview | CLIENT&quot;',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'rti_item6',
                'html' => 'Click on &quot;Add guests&quot; and add the VA(s) email address',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'rti_item7',
                'html' => 'Click on &quot;more options&quot;',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'rti_item8',
                'html' => 'Add 3 additional notifications and change &quot;Notification&quot; to &quot;Email&quot;',
                'value' => 'checked',
            ],
            [
                'kind' => 'note',
                'depth' => 3,
                'html' => 'Notification Times:',
            ],
            [
                'kind' => 'item',
                'depth' => 3,
                'name' => 'rti_item9',
                'html' => '0 minute before event',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 3,
                'name' => 'rti_item10',
                'html' => '10 minutes before event',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 3,
                'name' => 'rti_item11',
                'html' => '1 hour before event',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 3,
                'name' => 'rti_item12',
                'html' => '24 hours before event',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'rti_item12b',
                'html' => 'In the &quot;Add description&quot; box, copy and paste the following: <em>Change DATE and TIME to reflect the correct information</em>',
                'value' => 'checked',
            ],
            [
                'kind' => 'note',
                'depth' => 2,
                'html' => 'Hi there, <br> Congratulations! We&#39;re excited to let you know that you&#39;ve been selected to move forward to the second round of interviews. <br> Second Round of Interviews Date: DATE at TIME PST <br> Please ensure that you&#39;re available and prepared to join the interview on time. Here are a few pointers to help you get ready: <br> 1. Be on a computer or laptop: this ensures a stable connection and allows you to interact seamlessly. 2. Dress professionally: present yourself as you would in an in-person interview. 3. Test your equipment: if the interview is online, check your microphone, camera, and internet connection in advance. <br> We&#39;re excited for you to showcase your skills and qualifications in this upcoming round. If you have any questions or need assistance, feel free to reach out. <br> Best, Remote Leverage Hiring Team',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'rti_item12c',
                'html' => 'Save the invite',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Communication',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'rti_item13',
                'html' => 'Send a WhatsApp message to the VA(s) to let them know that they&#39;re scheduled for a 2nd Round with the client',
                'value' => 'checked',
            ],
            [
                'kind' => 'note',
                'depth' => 1,
                'html' => 'Hi VA. Great news! CLIENT would love to have you back for a second interview. Please check your email; I&#39;ve sent you a Calendar invite. Looking forward to seeing you then!',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Cleanup &amp; update failed candidates',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'rti_item14',
                'html' => 'Send &quot;Failed Client Interview&quot; to the VAs that were not selected',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'rti_item15',
                'html' => 'Remove the non-selected VAs from the Job',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'rti_item16',
                'html' => 'Update the Candidate Vetting Updates note and remove the VAs under &quot;Added&quot; and &quot;Approved by HM&quot;, and add them under &quot;Removed&quot; <em>i.e. 12/15 VI Aitor Tilla (failed)</em>',
                'value' => 'checked',
            ],
        ],
        'form' => [
            'id' => 'checklist-form-11',
            'action' => 'https://hooks.zapier.com/hooks/catch/10540636/2gyrtr0',
            'target' => 'hidden_iframe_11',
            'checklist_name' => '2nd Round Interviews Checklist',
            'require_name' => true,
        ],
    ],
    [
        'kind' => 'checklist',
        'title' => 'Collect Client&#39;s Payment Method',
        'blocks' => [
            [
                'kind' => 'text',
                'name' => 'hiringManagerName',
                'label' => 'Hiring Manager Name:',
                'placeholder' => 'Enter name...',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Setup',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item0',
                'html' => 'Open <a href="https://accounts.intuit.com/app/sign-in?app_group=QBO&amp;asset_alias=Intuit.accounting.core.qbowebapp&amp;app_environment=prod" target="_blank" rel="noopener">QuickBooks</a> and sign in',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item2',
                'html' => 'Share your screen so that the client can see what it is you&#39;re doing',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Pull up the customer&#39;s profile',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item1',
                'html' => 'Click on <strong>Customers</strong> at the top of the page',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item3',
                'html' => 'In the search bar, type in the name of the customer and click on it',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item4',
                'html' => 'Click <strong>Edit</strong> on the top right',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item5',
                'html' => 'Ensure that the following information is correct:',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'item5a',
                'html' => '<em>First name</em> and <em>Last name</em>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'item5b',
                'html' => '<em>Company name</em> (it isn&#39;t necessary to match the Company name with one that populates)',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'item5c',
                'html' => '<em>Email</em> and <em>Phone number</em>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item6',
                'html' => 'Scroll down to <strong>Payments</strong> section',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'item6a',
                'html' => 'Under <strong>Primary payment method</strong>, select ACH or Credit Card from the dropdown menu',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'item6b',
                'html' => 'Click <strong>Enter bank info</strong> button',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item7',
                'html' => 'Fill out the information with the client and repeat it back to them to ensure it is correct',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'item7a',
                'html' => 'Click <strong>Submit</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item8',
                'html' => 'Click <strong>Save</strong> at the bottom right corner',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Add a second payment method',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item10',
                'html' => 'Follow the above steps to create a new customer',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'item10a',
                'html' => 'Next to the Last name, add <strong>&#45; Backup</strong> <em>i.e. Mohammed &#45; Backup</em>',
                'value' => 'checked',
            ],
        ],
        'subtitle' => 'At the end of the Client Onboarding Meeting or when the client makes a decision on who to hire',
        'form' => [
            'id' => 'checklist-form-17',
            'action' => 'https://hooks.zapier.com/hooks/catch/10540636/2gyrtr0',
            'target' => 'hidden_iframe_17',
            'checklist_name' => 'Collect Client\'s Payment Method Checklist',
            'require_name' => true,
        ],
    ],
    [
        'kind' => 'checklist',
        'title' => 'Send Hiring Agreement',
        'blocks' => [
            [
                'kind' => 'text',
                'name' => 'hiringManagerName',
                'label' => 'Hiring Manager Name:',
                'placeholder' => 'Enter name...',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Payment method verification',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'inv_paymentMethod',
                'html' => 'If you&#39;re charging the client directly, check if their payment method is already on file',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'inv_pm1',
                'html' => 'Go to <a href="https://accounts.intuit.com/app/sign-in?app_group=QBO&amp;asset_alias=Intuit.accounting.core.qbowebapp&amp;app_environment=prod" target="_blank" rel="noopener">QuickBooks</a> and sign in',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'inv_pm2',
                'html' => 'Click on <strong>Customers</strong> at the top of the page',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'inv_pm3',
                'html' => 'Search for the customer and select it',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'inv_pm4',
                'html' => 'Click on <strong>Edit</strong> -&gt; scroll down to <strong>Payments</strong> -&gt; if ACH or Credit Card is already under <strong>Primary payment method</strong> and a button below it <em>(i.e. Business Checking: xxxxxx164)</em>, then we already have their payment method on file',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 3,
                'name' => 'inv_pm5',
                'html' => 'Payment method on file -&gt; continue below',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 3,
                'name' => 'inv_pm6',
                'html' => 'Payment method NOT on file -&gt; contact the client and follow the <strong>Collect Client&#39;s Payment Method</strong> checklist',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Create the invoice in QuickBooks',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'inv_item9',
                'html' => 'Go <a href="https://remoteleverage.com/tools/" target="_blank" rel="noopener">here</a> and click on <strong>Invoice Calculator</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'inv_item9a',
                'html' => 'Fill in all the required information',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 3,
                'name' => 'inv_item9b',
                'html' => 'To find the Deal ID, go to the Job -&gt; <strong>Related Deals</strong> tab -&gt; Open the Deal in a new tab. You&#39;ll see the ID at the top of the page, left-hand side <em>i.e. ID &#45; 2894</em>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'inv_item9c',
                'html' => 'Click <strong>Calculate</strong> and ensure the information is correct in the <strong>Invoice Breakdown</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 3,
                'name' => 'inv_item9d',
                'html' => 'Click <strong>Generate QuickBooks Invoice</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 3,
                'name' => 'inv_item9e',
                'html' => 'Input your email and click <strong>Confirm</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 3,
                'name' => 'inv_item9f',
                'html' => 'Click <strong>No</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Verify creation of invoices',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'inv_verify1',
                'html' => 'Go back to <a href="https://accounts.intuit.com/app/sign-in?app_group=QBO&amp;asset_alias=Intuit.accounting.core.qbowebapp&amp;app_environment=prod" target="_blank" rel="noopener">QuickBooks</a>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'inv_verify2',
                'html' => 'Click on <strong>Customers</strong> at the top of the page',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'inv_verify3',
                'html' => 'Search for the customer and select it',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'inv_verify4',
                'html' => 'Verify that the invoice(s) was(were) created successfully, along with the dates and amounts',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'inv_verify5',
                'html' => 'If created correctly, continue below',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Send Hiring Agreement with invoice breakdown',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'inv_item10',
                'html' => 'Click <strong>Download PDF</strong>. Open the file and take a screenshot of the invoice',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'inv_item10a',
                'html' => 'On Slack, in your personal DM, type in #agreements, find the correct agreement, and copy the link',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'inv_item10b',
                'html' => 'If it&#39;s a special agreement or unique exception, contact Nick on Slack',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'inv_item10c',
                'html' => 'Using Gmail, send the corresponding email template',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'inv_item11',
                'html' => 'No financing&#46;&#46;&#46;',
                'value' => 'checked',
            ],
            [
                'kind' => 'note',
                'depth' => 3,
                'html' => 'Email Template ►',
            ],
            [
                'kind' => 'template',
                'id' => 'email-content-7',
                'html' => '<strong>Subject:</strong> [Important] VA Placement Agreement: Remote Leverage <hr> Hi CLIENT, To move forward once VANAME has accepted the work offer, here are the next steps: 1. Please review and sign the recruitment agreement: <a href="https://form.jotform.com/253297363483163" target="_blank" rel="noopener">click here</a> 2. Once the agreement has been signed, and the VA has accepted your job offer, I will proceed to collect the payment (invoice breakdown below). 3. Once both steps are completed, I&#39;ll send over your VA&#39;s contact information along with a link to book your onboarding call, where we&#39;ll align on the start date, expectations, and next steps. SCREENSHOTOFTHEINVOICE To ensure you don&#39;t lose the candidate, please note that the recruiters will hold the applicant exclusively for 24 hours. During this time, they won&#39;t attend other interviews or receive other offers. After that window, they&#39;ll be reintroduced into the candidate pool, and we may need to restart the process if they&#39;re hired elsewhere. <em>Also, kindly let me know once the agreement has been signed, as I&#39;m not always automatically notified and want to move forward for you without delay.</em> Thanks again for choosing Remote Leverage. As a quick reminder, you&#39;ll receive a 30% discount on any additional hires you make with us moving forward. Feel free to reach out if you need help filling other roles! Best,',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'inv_item11_financing',
                'html' => 'Financing&#46;&#46;&#46;',
                'value' => 'checked',
            ],
            [
                'kind' => 'note',
                'depth' => 3,
                'html' => 'Email Template ►',
            ],
            [
                'kind' => 'template',
                'id' => 'email-content-7-financing',
                'html' => '<strong>Subject:</strong> [Important] VA Placement Agreement: Remote Leverage <hr> Hi CLIENT, To move forward once VANAME has accepted the work offer, here are the next steps: 1. Please review and sign the recruitment agreement: <a href="https://form.jotform.com/253297363483163" target="_blank" rel="noopener">click here</a>. To review and sign the Financing Addendum, <a href="https://form.jotform.com/253216864842159" target="_blank" rel="noopener">click here</a> 2. Once the agreements have been signed and the VA has accepted the work offer, I&#39;ll charge the payment method on file for the hiring fee (breakdown below). 3. Once both steps are completed, I&#39;ll send over your VA&#39;s contact information along with a link to book your onboarding call, where we&#39;ll align on the start date, expectations, and next steps. SCREENSHOTOFTHEINVOICE To ensure you don&#39;t lose the candidate, please note that the recruiters will hold the applicant exclusively for 24 hours. During this time, they won&#39;t attend other interviews or receive other offers. After that window, they&#39;ll be reintroduced into the candidate pool, and we may need to restart the process if they&#39;re hired elsewhere. <em>Also, kindly let me know once the agreement has been signed, as I&#39;m not always automatically notified and want to move forward for you without delay.</em> Thanks again for choosing Remote Leverage. As a quick reminder, you&#39;ll receive a 30% discount on any additional hires you make with us moving forward. Feel free to reach out if you need help filling other roles! Best,',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'inv_item12',
                'html' => 'Send a SMS to the client via Quo',
                'value' => 'checked',
            ],
            [
                'kind' => 'note',
                'depth' => 1,
                'html' => 'Hi CLIENT. I just emailed you a link to the agreement for you to sign. That&#39;s all that&#39;s needed on your end before we can get you and VANAME connected. Please let me know once it&#39;s done!',
            ],
        ],
        'form' => [
            'id' => 'checklist-form-7',
            'action' => 'https://hooks.zapier.com/hooks/catch/10540636/2gyrtr0',
            'target' => 'hidden_iframe_7',
            'checklist_name' => 'Send Hiring Agreement for the Client to Sign Checklist',
            'require_name' => true,
        ],
    ],
    [
        'kind' => 'checklist',
        'title' => 'Work Offer',
        'blocks' => [
            [
                'kind' => 'text',
                'name' => 'hiringManagerName',
                'label' => 'Hiring Manager Name:',
                'placeholder' => 'Enter name...',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => '24-Hour Check',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'jo_item0',
                'html' => 'Ensure that the VA wasn&#39;t sent a job offer within the past 24 hours by checking their profile in the CRM. If there was, do not send another job offer',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Collect job offer details from the client',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'jo_item1',
                'html' => 'Position Title',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'jo_item2',
                'html' => 'Full Time/Part Time',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'jo_item6',
                'html' => 'Compensation',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'jo_item3',
                'html' => 'Work schedule <em>i.e. Monday to Friday 8am to 5pm PST</em>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'jo_item4',
                'html' => 'Start date',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'jo_item5',
                'html' => 'Payment schedule <em>i.e. Bi-Weekly (15th and 30th)</em>',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Create job offer',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'jo_item_open_form',
                'html' => 'Open the <a href="https://form.jotform.com/252519037487060" target="_blank" rel="noopener">Work Offer Form</a>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'jo_item_fill_form',
                'html' => 'Fill out each field and click <strong>Submit</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'jo_item_check_email',
                'html' => 'Check your email and open &quot;Remote Leverage &#45; Work Offer | Important&quot; -&gt; click on the <strong>Review &amp; Sign Document</strong> button',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'jo_item_fill_wo',
                'html' => 'Fill out job offer details',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'jo_item_sign_complete',
                'html' => 'Click <strong>Sign &amp; Complete</strong> -&gt; click <strong>Accept &amp; Send</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'jo_item_notify_va',
                'html' => 'Notify the VA by sending a WhatsApp message and calling them',
                'value' => 'checked',
            ],
            [
                'kind' => 'note',
                'depth' => 1,
                'html' => 'Hi VANAME. This is YOURNAME from Remote Leverage. Congratulations! CLIENT would like to extend the offer to you. Please check your inbox at your earliest convenience (it&#39;s an email from Jotform), and let me know as soon as you sign it, please. It&#39;s important to note that as long as you have a pending work offer, you won&#39;t be able to interview with any other clients.',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Documentation &amp; Slack update',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'jo_item15',
                'html' => 'Go to the VA&#39;s profile in the CRM and create a note',
                'value' => 'checked',
            ],
            [
                'kind' => 'note',
                'depth' => 1,
                'html' => 'MM/DD YourInitials WO ($??/hr) sent for ClientName <em>i.e. 12/15 IV WO ($12/hr) sent for Susana Oria</em>',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'jo_item_wo_name',
                'html' => 'Edit the VA&#39;s name to add <strong>WO:</strong> before the first name <em>i.e. WO: Susana</em>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'jo_item16',
                'html' => 'Post a Slack update in #operations chat',
                'value' => 'checked',
            ],
            [
                'kind' => 'note',
                'depth' => 1,
                'html' => ':alert: CLIENT offering job to VANAME for $AMOUNT/hr. Offer sent, VA informed. :alert:',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'jo_item17',
                'html' => 'Open every Job the VA is assigned to and remove them from <strong>Candidate Vetting Updates</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'jo_item17a',
                'html' => 'Add the VA to the <strong>Removed</strong> section: MM/DD YourInitials VAsName (hired by ClientName) <em>i.e. 01/09 VI Selena Kyle (hired by Victor von Doom)</em>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'jo_item17b',
                'html' => 'Remove the VA from the <strong>Added</strong> section',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'jo_item17c',
                'html' => 'Remove the VA from the ticket',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'jo_item18',
                'html' => 'Post a Slack update in #operations chat to ask other HMs to remove the VA from their interview invites. Tag the HM and write the name of their client next to it',
                'value' => 'checked',
            ],
            [
                'kind' => 'note',
                'depth' => 1,
                'html' => 'Please remove Selena Kyle from your invites @Nock &#45; God Emperor of Ball Busting Cerys Guerrero @Miguel Idris Hodge @Dave Fintan Barton @Princess Ebony Ball',
            ],
        ],
        'form' => [
            'id' => 'checklist-form-6',
            'action' => 'https://hooks.zapier.com/hooks/catch/10540636/2gyrtr0',
            'target' => 'hidden_iframe_6',
            'checklist_name' => 'Job Offer Checklist',
            'require_name' => true,
        ],
    ],
    [
        'kind' => 'checklist',
        'title' => 'Collect Payment',
        'blocks' => [
            [
                'kind' => 'text',
                'name' => 'hiringManagerName',
                'label' => 'Hiring Manager Name:',
                'placeholder' => 'Enter name...',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Verify if both the Hiring Agreement and Job Offer have been signed',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item8',
                'html' => 'If the client informs you that they signed the hiring agreement&#46;&#46;&#46;',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'item8a',
                'html' => 'Find the Stripe notification in the #remote-leverage-group-chat channel in Slack; it looks like this',
                'value' => 'checked',
            ],
            [
                'kind' => 'note',
                'depth' => 3,
                'html' => 'New submission for: <a href="https://nothing.com" target="_blank" rel="noopener">Agreement &#45; New VA Bundle / Individual Packages 40% &#45; Deposit Paid</a>',
            ],
            [
                'kind' => 'group',
                'depth' => 3,
                'title' => 'Name:',
            ],
            [
                'kind' => 'note',
                'depth' => 3,
                'html' => 'Hugh Mungus',
            ],
            [
                'kind' => 'group',
                'depth' => 3,
                'title' => 'Email:',
            ],
            [
                'kind' => 'note',
                'depth' => 3,
                'html' => '<a href="mailto:nothing@blah.com" target="_blank" rel="noopener">hugh.mungus@behappy.net</a>',
            ],
            [
                'kind' => 'group',
                'depth' => 3,
                'title' => 'Which package would you like to sign up for? Bundles are cheaper than hiring individually if you plan on hiring more VAs for any roles in the next 12 months.:',
            ],
            [
                'kind' => 'note',
                'depth' => 3,
                'html' => '3 VA Bundle &#45; $10k ($3.33k/VA)',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'item8b',
                'html' => 'If you can&#39;t find the Jotform notification, ask <strong>@Nick &#45; God Emperor of Ball Busting</strong> on Slack to verify on Jotform whether the agreement was signed by the client',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item8c',
                'html' => 'Verify that the VA has signed the Job Offer',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'item8d',
                'html' => 'You&#39;ll find the notification in the #work-offers channel on Slack',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Collect payment',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item20',
                'html' => 'Go to <a href="https://accounts.intuit.com/app/sign-in?app_group=QBO&amp;asset_alias=Intuit.accounting.core.qbowebapp&amp;app_environment=prod" target="_blank" rel="noopener">QuickBooks</a> and sign in',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item21',
                'html' => 'At the top of the page, click on <strong>Customers</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item22',
                'html' => 'Click on the <strong>Search</strong> field, type in the customer&#39;s name, and click it',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item23',
                'html' => 'Look for the correct invoice to charge, and, on the right, click on <strong>Receive payment</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'item23a',
                'html' => 'Make sure that <strong>Charge new payment</strong> is selected, and select the correct <strong>Payment method</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'item23b',
                'html' => 'Under <strong>Outstanding Transactions</strong>, make sure that the correct Invoice is checked off',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item24',
                'html' => 'Confirm the amount being charged is accurate, and click on <strong>Charge and close</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Verify whether the invoice was paid successfully',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item27',
                'html' => 'Go to <a href="https://merchantcenter.intuit.com/" target="_blank" rel="noopener">QuickBooks Merchant Center</a> and login using your QuickBooks credentials',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item28',
                'html' => 'Under <strong>Activity &amp; Reports</strong>, click on <strong>Transactions</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item29',
                'html' => 'Set the date to <strong>Today</strong> and click on the transaction you just made',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'item29a',
                'html' => 'Under <strong>STATUS</strong>, it should say <em>Pending</em> or <em>Completed</em>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item30',
                'html' => 'Take a screenshot and post it in #operations chat on Slack',
                'value' => 'checked',
            ],
            [
                'kind' => 'note',
                'depth' => 1,
                'html' => ':pepe-money-rain::pepemoney: Invoice collected for CLIENT :pepemoney::pepe-money-rain: Individual hire/3-VA Bundle, financed. Payment 1/3 SCREENSHOT',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Send receipt of the paid invoice',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item31',
                'html' => 'Go back to <a href="https://accounts.intuit.com/app/sign-in?app_group=QBO&amp;asset_alias=Intuit.accounting.core.qbowebapp&amp;app_environment=prod" target="_blank" rel="noopener">QuickBooks</a> and click on <strong>Customers</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item32',
                'html' => 'Click on the <strong>Search</strong> field under <strong>Customer</strong>, type in the customer&#39;s name, and click it',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item34',
                'html' => 'Find the right invoice under today&#39;s date, the one that says &quot;Paid&quot;, and click <strong>View/Edit</strong> on the right-hand side',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item35',
                'html' => 'Click <strong>Review and send</strong> -&gt; <strong>Yes</strong> -&gt; <strong>Send Invoice</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Change Deal stage',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item37',
                'html' => 'Go back to the Job and navigate to the <strong>Related Deals</strong> tab -&gt; click on <strong>won</strong>, in the green field -&gt; change <strong>Deal Stage</strong> to <strong>Won &amp; Fulfilled</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Final steps',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'item36',
                'html' => 'Follow the <strong>Invoice Paid</strong> checklist',
                'value' => 'checked',
            ],
        ],
        'form' => [
            'id' => 'checklist-form-16',
            'action' => 'https://hooks.zapier.com/hooks/catch/10540636/2gyrtr0',
            'target' => 'hidden_iframe_16',
            'checklist_name' => 'Collect Payment Checklist',
            'require_name' => true,
        ],
    ],
    [
        'kind' => 'checklist',
        'title' => 'SplitIt Payment',
        'blocks' => [
            [
                'kind' => 'text',
                'name' => 'hiringManagerName',
                'label' => 'Hiring Manager Name:',
                'placeholder' => 'Enter name...',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Note:',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'splitit_1',
                'html' => 'Go to <a href="https://hub.splitit.com/login" target="_blank" rel="noopener">SplitIt</a> and sign in',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'splitit_2',
                'html' => 'On the left-hand side, click <strong>Create Plan</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'splitit_3',
                'html' => 'Under <strong>Total Plan Amount</strong>, type in the full amount of the invoice',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'splitit_3a',
                'html' => '<strong><u>IMPORTANT</u></strong> Make sure to add 10% of the invoice to the amount',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'splitit_4',
                'html' => 'Under <strong>Order Id / Number</strong>, type in the name of the client and the Deal ID <em>i.e. Matt Teeris &#45; 16706</em>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'splitit_5',
                'html' => 'Under <strong>Installment Options</strong>, click <strong>Use Default</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'splitit_6',
                'html' => 'Click <strong>Continue</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'splitit_7',
                'html' => 'You&#39;ll see <strong>Share Payment Link</strong> populate',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'splitit_8',
                'html' => 'Copy the link and share it with the client either via SMS or in the Zoom Chat if you&#39;re in a meeting with the client',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'splitit_9',
                'html' => 'Click <strong>Email</strong> and type in the client&#39;s email and click <strong>Send</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'splitit_9a',
                'html' => 'Let the client know that they&#39;ll get an email from SplitIt Team, subject &quot;Remote Leverage: Complete your purchase&quot;. Have them open it and click the green box that says <strong>Complete your purchase</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'splitit_9b',
                'html' => 'If they don&#39;t see it in their inbox, have them check their Spam folder',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'splitit_10',
                'html' => 'Once the client informs you that they&#39;ve done this, on the left-hand side, click <strong>Payment Plans</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'splitit_11',
                'html' => 'Look for the client&#39;s name under <strong>Consumer Name</strong> and click on it',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'splitit_12',
                'html' => 'Under <strong>Order Status</strong>, you should see <strong>In Progress</strong> under <strong>Status</strong>, and <strong>Approved</strong> under <strong>Fraud Status</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'splitit_13',
                'html' => 'Take a screenshot and post it in #operations chat',
                'value' => 'checked',
            ],
            [
                'kind' => 'template',
                'id' => 'splitit-template-box',
                'html' => ':pepe-money-rain::pepemoney: Invoice collected for CLIENT :pepemoney::pepe-money-rain: SplitIt. Individual hire/3-VA Bundle SCREENSHOT',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Create QuickBooks invoice',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'splitit_qb1',
                'html' => 'Go <a href="https://remoteleverage.com/tools/" target="_blank" rel="noopener">here</a> and click on <strong>Invoice Calculator</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'splitit_qb2',
                'html' => 'Fill in all the required information',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'splitit_qb2a',
                'html' => 'Under <strong>Do you want to finance this invoice?</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 3,
                'name' => 'splitit_qb2a1',
                'html' => '<strong>Finance?</strong> -&gt; <strong>Yes</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 3,
                'name' => 'splitit_qb2a2',
                'html' => '<strong>Financing Mode</strong> -&gt; <strong>Splitit.com</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 3,
                'name' => 'splitit_qb2a3',
                'html' => '<strong>Financing Fee %</strong> -&gt; type whatever the % is for your client (default is 10)',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'splitit_qb3',
                'html' => 'Click <strong>Calculate</strong> and ensure the information is correct in the Invoice Breakdown',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'splitit_qb4',
                'html' => 'Click <strong>Generate QuickBooks Invoice</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'splitit_qb4a',
                'html' => 'Input your email and click <strong>Confirm</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'splitit_qb4b',
                'html' => 'Click <strong>No</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Record the payment on Quickbooks',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'splitit_rec1',
                'html' => 'Go to <a href="https://accounts.intuit.com/app/sign-in?app_group=QBO&amp;asset_alias=Intuit.accounting.core.qbowebapp&amp;app_environment=prod" target="_blank" rel="noopener">QuickBooks</a> and sign in',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'splitit_rec2',
                'html' => 'At the top of the page, click on <strong>Customers</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'splitit_rec3',
                'html' => 'Click on the Search field, type in the customer&#39;s name, and click it',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'splitit_rec4',
                'html' => 'Look for the correct invoice to charge, and, on the right-hand side, click on <strong>Receive payment</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'splitit_rec5',
                'html' => 'Make sure that <strong>Record payment</strong> is selected',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'splitit_rec6',
                'html' => 'Under <strong>Outstanding Transactions</strong>, make sure that the correct Invoice is checked off',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'splitit_rec6a',
                'html' => 'Confirm that both that amount and the one that was charged on SplitIt are the same',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'splitit_rec7',
                'html' => 'In the <strong>Memo</strong> box, towards the bottom, type in <em>Financed via splitit.com</em>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'splitit_rec8',
                'html' => 'Click on <strong>Record and close</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'splitit_rec9',
                'html' => 'Back in the Customer profile, find the right invoice under today&#39;s date, the one that says &quot;Paid&quot;, and click <strong>View/Edit</strong> on the right-hand side',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'splitit_rec10',
                'html' => 'Click <strong>Review and send</strong> -&gt; <strong>Yes</strong> -&gt; <strong>Send Invoice</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Final steps',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'splitit_final1',
                'html' => 'Follow the <strong>Invoice Paid</strong> checklist',
                'value' => 'checked',
            ],
        ],
        'form' => [
            'id' => 'checklist-form-2346',
            'action' => 'https://hooks.zapier.com/hooks/catch/10540636/2gyrtr0',
            'target' => 'hidden_iframe_2346',
            'checklist_name' => 'SplitIt Payment Checklist',
            'require_name' => true,
        ],
    ],
    [
        'kind' => 'checklist',
        'title' => 'Invoice Paid',
        'blocks' => [
            [
                'kind' => 'text',
                'name' => 'hiringManagerName',
                'label' => 'Hiring Manager Name:',
                'placeholder' => 'Enter name...',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Update the Job',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ip_item2',
                'html' => 'Go to the Job',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ip_item3',
                'html' => 'Update the <strong>Live Updates</strong> note',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'ip_item3a',
                'html' => 'If paid in full → <em>Hired Alan Brito at $10/hr. Full payment</em>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'ip_item3b',
                'html' => 'If split payment → <em>Hired Alan Brito at $10/hr. Split Payment. Next payment on MM/DD $AMOUNT</em>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'ip_item3c',
                'html' => 'If Financing → <em>Hired Alan Brito at $10/hr. Financing. Next payments on MM/DD and MM/DD $AMOUNT</em>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ip_item4',
                'html' => 'Make sure that all Job fields are updated correctly',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'ip_item4a',
                'html' => 'Client Time Zone',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'ip_item4b',
                'html' => 'Payment Method Saved on File',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'ip_item4c',
                'html' => 'Hire Type',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'ip_item4d',
                'html' => 'Hiring Fee Percentage',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'ip_item4e',
                'html' => 'Payment Terms',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'ip_item4f',
                'html' => 'Job Type',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ip_item5',
                'html' => 'Update the <strong>Job Status</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'ip_item5a',
                'html' => 'If paid in full → <strong>Closed</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'ip_item5b',
                'html' => 'If split payment or financing → <strong>Closed &#45; Split Payment</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Payment tracking',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ip_item6',
                'html' => 'If more payments need to be collected (split payment or financing), write a reminder in #operations channel on Slack:',
                'value' => 'checked',
            ],
            [
                'kind' => 'note',
                'depth' => 1,
                'html' => '/remind #operations to collect payment ?/? for CLIENT on DATE <em>i.e. /remind #operations to collect payment 2/2 for Hugh Mongous on March 3</em>',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Update status of hired VA',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ip_item7',
                'html' => 'If there are more VAs in the ticket, other than the one that was hired, go to the <strong>Client interview</strong> checklist and follow the steps under the &quot;Update failed candidates&quot; section',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ip_item8',
                'html' => 'Open the hired VA&#39;s profile',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'ip_item8a',
                'html' => 'Change <strong>Candidate Stage</strong> to <strong>Placed</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'ip_item8b',
                'html' => 'Click on Edit (pencil icon next to the flame icon at the top) and add &quot;Hired:&quot; in front of the first name <em>i.e. Hired: Alan Brito</em> and click <strong>Submit</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'ip_item8c',
                'html' => 'Add a new note to the VA&#39;s profile <em>02/03 VI &#45; Hired by Hugh Mongous at $10/hr</em> and <strong>Save</strong> it',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ip_item9',
                'html' => 'Remove the hired VA from all other Jobs',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'ip_item9a',
                'html' => 'Under the <strong>Assigned Jobs</strong> tab, open each Job the VA is assigned to (except the one they were hired for)',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'ip_item9b',
                'html' => 'Drop an update in #operations chat on Slack to inform the team and request other HMs to remove the VA from their Calendar invites',
                'value' => 'checked',
            ],
            [
                'kind' => 'note',
                'depth' => 2,
                'html' => ':alert: Alan Brito hired by Hugh Mongous. Please remove him from your Calendar invites :alert: @Manny Bruce Wayne @Princess Harley Quinn @David Harvey Dent',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ip_item10',
                'html' => 'Follow the <strong>Remove Candidates from Job &#45; HM Management Tool</strong> checklist',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Send VA&#39;s info to client',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ip_item11',
                'html' => 'Directly through Gmail, send an email to the client with the VA&#39;s information. Make sure to CC the VA.',
                'value' => 'checked',
                'template_id' => 'ip-email-1600',
            ],
            [
                'kind' => 'template',
                'id' => 'ip-email-1600',
                'html' => '<strong>Subject:</strong> Your New Hire [Remote Leverage] <hr> <p>Hi CLIENT,</p> <p>Great news: VANAME has accepted your work offer! Here is VANAME contact information:</p> <p>Name: VANAME<br>Email: VAEMAIL<br>Phone (WhatsApp): VANUMBER</p> <p><strong>Next Step: Book your onboarding call with VANAME</strong><br>Please use the link below to schedule an onboarding call. This call will allow us to align on the official start date, set clear expectations, and address any questions you may have before VANAME gets started. While you&#39;re welcome to communicate directly with VANAME, we recommend the onboarding call to ensure a smooth start and proper handoff. To schedule your onboarding call, please <a href="https://calendly.com/d/cth5-z99-vj3/virtual-assistant-client-onboarding-w-account-exec?month=2026-01" target="_blank" rel="noopener">click here</a>.</p> <p>P.S. We&#39;ve created a helpful guide that covers common topics like how to pay VANAME, use time-tracking tools, set up training, and more. You can access it by <a href="https://remoteleverage.com/vaonboardingguide" target="_blank" rel="noopener">clicking here</a>.</p> <p>P.P.S. As a friendly reminder, you&#39;re also eligible for a 30% discount on any additional hires going forward. If you need help filling another position, feel free to reach out.</p> <p>Thank you for choosing Remote Leverage!</p> <p>Best,</p>',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => '📋 Upsell Inquiry (Required)',
            ],
            [
                'kind' => 'text',
                'name' => 'upsell_jobName',
                'label' => 'Client name and role:',
                'placeholder' => 'Enter client name and role...',
                'required' => true,
            ],
            [
                'kind' => 'text',
                'name' => 'upsell_jobId',
                'label' => 'Job ID:',
                'placeholder' => 'Enter job ID...',
                'required' => true,
            ],
            [
                'kind' => 'textarea',
                'name' => 'upsell_whyNoPackage',
                'id' => 'upsell_whyNoPackage-1600',
                'label' => 'Why were you unable to sell the Performance &amp;amp; Payroll Package?',
                'placeholder' => 'Explain why...',
                'rows' => '3',
                'required' => true,
            ],
            [
                'kind' => 'textarea',
                'name' => 'upsell_whyNoBundle',
                'id' => 'upsell_whyNoBundle-1600',
                'label' => 'Why were you unable to upsell to a higher bundle?',
                'placeholder' => 'Explain why...',
                'rows' => '3',
                'required' => true,
            ],
        ],
        'form' => [
            'id' => 'checklist-form-1600',
            'action' => 'https://hooks.zapier.com/hooks/catch/10540636/2gyrtr0',
            'target' => 'hidden_iframe_1600',
            'checklist_name' => 'Invoice Paid Checklist',
            'require_name' => true,
            'upsell_action' => 'https://hooks.zapier.com/hooks/catch/26138149/u03014q/',
        ],
    ],
    [
        'kind' => 'checklist',
        'title' => 'VA Replacement',
        'blocks' => [
            [
                'kind' => 'text',
                'name' => 'hiringManagerName',
                'label' => 'Hiring Manager Name:',
                'placeholder' => 'Enter name...',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Confirm VA removal',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'rv_item0a',
                'html' => 'This MUST be completed before moving forward',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'rv_item0a_sub',
                'html' => 'Confirm with the client whether the VA has either quit or been fired. If none of the two, inform the client that the VA must first have left the company before we can move on with scheduling interviews for a replacement.',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'HMs: AE hand-off (if not done already)',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'rv_item0',
                'html' => 'Email the client to share the AEs&#39; Calendly link for them to schedule interviews for the replacement. Use the following template',
                'value' => 'checked',
            ],
            [
                'kind' => 'template',
                'id' => 'email-content-9',
                'html' => '<strong>Subject:</strong> Book interviews for your VA replacement [Remote Leverage] <hr> <p>Hi CLIENT,</p> <p>Now that we made the first hire, the next step is to do an onboarding call with an Account Executive and your VA to help you onboard the VA you just hired.</p> <p>We assign clients Account Executives to make sure that you have a permanent person to work with for any additional hires, replacements, or really any type of support you need, their job is to give you top-tier support and white-glove service going forward as you continue to work with us.</p> <p>I&#39;ll also be happy to help with anything if you need me as well. Feel free to keep my email and ping me if needed, but I&#39;m sure they&#39;ll take great care of you!</p> <p>Please book a time to do the onboarding <a href="https://calendly.com/d/cxhr-4bv-f2j/remote-leverage-virtual-assistant-interview?month=2026-01" target="_blank" rel="noopener">here</a> with an Account Executive.</p> <p>Thanks!</p>',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'rv_item0b',
                'html' => 'Go to the Job',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'rv_item0b1',
                'html' => 'Look for the Contact bubble next to <strong>JOB CONTACT(S)</strong> and click on it',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'rv_item0b2',
                'html' => 'Make sure that your name is in the field next to <strong>Hiring Manager Assigned</strong>. If it&#39;s not, type it in and click on the check mark to save it.',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'rv_item0c',
                'html' => 'Your job here is done!',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Once a replacement VA has been chosen',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'rv_inv1',
                'html' => 'Go <a href="https://remoteleverage.com/tools/" target="_blank" rel="noopener">here</a> and click on <strong>Invoice Calculator</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'rv_inv1a',
                'html' => 'Fill in all the required information',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 3,
                'name' => 'rv_inv1a1',
                'html' => 'To find the Deal ID, go to the Job -&gt; <strong>Related Deals</strong> tab -&gt; Open the Deal in a new tab. You&#39;ll see the ID at the top of the page, left-hand side <em>i.e. ID &#45; 2894</em>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'rv_inv1b',
                'html' => 'Under <strong>Type</strong>, below <strong>VA 1</strong>, select <strong>Replacement (no fee)</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'rv_inv1c',
                'html' => 'Click <strong>Calculate</strong> -&gt; <strong>Submit Replacement to Database</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'rv_inv1d',
                'html' => 'Input your email and click <strong>Confirm</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Update Job',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'rv_item1',
                'html' => 'In the Job, update the <strong>Live Updates</strong> note <em>i.e. MM/DD YourInitials &#45; VaNameFullName quit/was fired</em>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'rv_item2',
                'html' => 'Add <strong>&#40;R&#41;</strong> to the Job name, at the beginning <em>i.e. &#40;R&#41; Selena Kyle (CSR)</em>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'rv_item2b',
                'html' => 'Change ownership of the Job to yourself',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'rv_item2b1',
                'html' => 'Click on the original HM&#39;s name, next to the date and time the last time the Job was edited, type in your name, and click it',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'rv_item2b2',
                'html' => 'Click <strong>Submit</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'rv_item3',
                'html' => 'Go to the VA&#39;s profile and change the <strong>Candidate Stage</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'rv_item3a',
                'html' => 'If they were fired -&gt; <strong>Fired</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'rv_item3b',
                'html' => 'If they quit -&gt; <strong>Disqualified: Check Notes</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'rv_item4',
                'html' => 'While still in the VA&#39;s profile, add a note with either &quot;Fired&quot; or &quot;Quit&quot; <em>i.e. 01/07 IV &#45; Fired</em>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'rv_item4a',
                'html' => 'Click the edit icon (next to the member icon, top-right) and change the VA&#39;s first name to add <strong>Fired:</strong>, whether they quit or were fired <em>Fired: Clark</em>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'rv_item5',
                'html' => 'Leave a message in #operations chat on Slack, and @ the recruiter in charge of the Job, as well as the Head Recruiter',
                'value' => 'checked',
            ],
            [
                'kind' => 'note',
                'depth' => 1,
                'html' => ':warning: Client Update: ClientName (hyperlink the Job) VAName was fired/quit. Sent ticket back to HFS @Gabby @Recruiter',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Once a VA has been hired',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'rv_hired1',
                'html' => 'Follow steps under <strong>Update status of hired VA</strong> and <strong>Send VA&#39;s info to client</strong> in <strong>Invoice Paid</strong> checklist',
                'value' => 'checked',
            ],
        ],
        'form' => [
            'id' => 'checklist-form-9',
            'action' => 'https://hooks.zapier.com/hooks/catch/10540636/2gyrtr0',
            'target' => 'hidden_iframe_9',
            'checklist_name' => 'Replacement VA Checklist',
            'require_name' => true,
        ],
    ],
    [
        'kind' => 'checklist',
        'title' => 'Returning Client',
        'blocks' => [
            [
                'kind' => 'text',
                'name' => 'hiringManagerName',
                'label' => 'Hiring Manager Name:',
                'placeholder' => 'Enter name...',
            ],
            [
                'kind' => 'note',
                'depth' => 0,
                'html' => 'This is to be done whenever a client comes back wanting to hire more VAs.',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'rc_item1',
                'html' => 'Open the Contact page for your client (red icon in CRM)',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'rc_item1a',
                'html' => 'Copy the name of the client as it appears. It could be either only CLIENTNAME <em>i.e. Matt Tistekols</em> or CLIENTNAME &#45; COMPANY <em>i.e. Matt Tistekols &#45; Tistekols Distributions</em>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'rc_item2',
                'html' => 'Under the <strong>Related Deals</strong> tab, click <strong>Add Deal</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'rc_item3',
                'html' => 'Fill in the following fields:',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'rc_item3a',
                'html' => 'Name: 2/ CLIENTNAME or CLIENTNAME &#45; COMPANY <em>The number before the / depends on the deal number this is</em>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'rc_item3b',
                'html' => 'Stage: Repeat Client &#45; Hiring Again',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'rc_item3c',
                'html' => 'Value: 0',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'rc_item3d',
                'html' => 'Close Date: Today',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'rc_item3e',
                'html' => 'Owner: Your name',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'rc_item3f',
                'html' => 'Click <strong>Submit</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'rc_item4',
                'html' => 'Follow <strong>New Client Assigned</strong> checklist -&gt; <strong>Follow this list when a Job isn&#39;t created automatically</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'rc_item5',
                'html' => 'Once the Job is created:',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'rc_item5a',
                'html' => 'Go to <strong>Related Deals</strong> tab and click <strong>Link To Existing Deal</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'rc_item5b',
                'html' => 'In the search bar, type in the name of the client',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'rc_item5c',
                'html' => 'Select the new Deal you just created <em>i.e. 2/ Matt Tistekols &#45; Tistekols Distributions</em>',
                'value' => 'checked',
            ],
        ],
        'form' => [
            'id' => 'checklist-form-30',
            'action' => 'https://hooks.zapier.com/hooks/catch/10540636/2gyrtr0',
            'target' => 'hidden_iframe_30',
            'checklist_name' => 'Returning Client Checklist',
            'require_name' => true,
        ],
    ],
    [
        'kind' => 'checklist',
        'title' => 'AE Handoff',
        'blocks' => [
            [
                'kind' => 'text',
                'name' => 'hiringManagerName',
                'label' => 'Hiring Manager Name:',
                'placeholder' => 'Enter name...',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'After the first hire',
            ],
            [
                'kind' => 'group',
                'depth' => 1,
                'title' => 'If you&#39;re on the call with the client&#46;&#46;&#46;',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'ae_item1',
                'html' => 'Book VA onboarding meeting using the Account Executive Round Robin Calendly <a href="https://calendly.com/d/cth5-z99-vj3/virtual-assistant-client-onboarding-w-account-exec?month=2026-02" target="_blank" rel="noopener">here</a>.',
                'value' => 'checked',
            ],
            [
                'kind' => 'note',
                'depth' => 2,
                'html' => '<em>Only use personal AE links if you need someone specific. Otherwise, it should be the Round Robin.</em>',
            ],
            [
                'kind' => 'group',
                'depth' => 1,
                'title' => 'If you&#39;re doing this via email&#46;&#46;&#46;',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'ae_item2',
                'html' => 'Send the link to the client so they can book the meeting themselves. This is in the <strong>Invoice Paid</strong> checklist -&gt; <em>Send VA&#39;s info to client</em>',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 1,
                'title' => 'Once scheduled&#46;&#46;&#46;',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'ae_item3',
                'html' => 'Inform the client who they were assigned to and remind them that the AE will be their point of contact moving forward',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'ae_item4',
                'html' => 'Go to the Contact page in the CRM',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 3,
                'name' => 'ae_item4a',
                'html' => 'Make sure that your name is in the <strong>Hiring Manager</strong> field. This makes you the original HM of the client',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'ae_item4b',
                'html' => 'Go to the <a href="https://remoteleverage.com/tools/" target="_blank" rel="noopener">Tools</a> page and click on <strong>AE Management</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 3,
                'name' => 'ae_item4b1',
                'html' => 'Select the <strong>AE Notes</strong> tab',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 3,
                'name' => 'ae_item4b2',
                'html' => 'Find the corresponding Job and fill out the different fields (the more detailed the notes, the better)',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 3,
                'name' => 'ae_item4b3',
                'html' => 'Click <strong>Submit AE Notes</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 1,
                'title' => 'If an existing client needs a replacement&#46;&#46;&#46;',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'ae_item5',
                'html' => 'Follow the <strong>VA Replacement</strong> checklist',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 1,
                'title' => 'If an existing client needs to hire for a new role&#46;&#46;&#46;',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'ae_item6',
                'html' => 'Email the client to share the AEs&#39; Calendly link for them to schedule an onboarding to go over the requirements for the new role. Use the following template',
                'value' => 'checked',
            ],
            [
                'kind' => 'template',
                'id' => 'email-content-ae6',
                'html' => '<strong>Subject:</strong> [Remote Leverage] Book an onboarding call for your new hire <hr> <p>Hi CLIENT,</p> <p>Happy to hear from you again!</p> <p>We assign clients Account Executives to make sure that you have a permanent person to work with for any additional hires. Their job is to give you top-tier support and white-glove service going forward as you continue to work with us.</p> <p>I&#39;ll also be happy to help with anything if you need me as well. Feel free to keep my email and ping me if needed, but I&#39;m sure they&#39;ll take great care of you!</p> <p>Please book a time to have a quick onboarding call with an Account Executive, <a href="https://calendly.com/d/cxgj-5gs-kts/remote-leverage-onboarding-applicant-criteria-rt?from=slack&amp;month=2026-04" target="_blank" rel="noopener">here</a>.</p> <p>Thanks!</p>',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'For AEs: VA &lt;&gt; Client Onboarding',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'aeo_item1',
                'html' => 'Make sure that the VA is invited to the event',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'aeo_item2',
                'html' => 'Join the call; make sure that the VA joins as well',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'aeo_item3',
                'html' => 'Bring the client in before the VA, introduce yourself as their new contact at Remote Leverage, instilling confidence and trust',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'aeo_item4',
                'html' => 'Let the client know that the meeting is for them and the VA to chat about a payment method that works for both parties, official start date, and ask each other any last-minute questions. This is, essentially, the final handshake.',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'aeo_item5',
                'html' => 'Bring the VA in and congratulate both client and VA on the new hire',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'aeo_item6',
                'html' => 'Once they are done, congratulate them again, and let the VA know they can jump off. Ask the client if they can stay with you in the room for a few more minutes',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'aeo_item7',
                'html' => 'Pull up the VA Onboarding Presentation <a href="#" target="_blank" rel="noopener">here</a>, and share your screen',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'aeo_item8',
                'html' => 'Go through each slide and upsell the Bundle and the Performance &amp; Payroll Package',
                'value' => 'checked',
            ],
        ],
        'form' => [
            'id' => 'checklist-form-9765',
            'action' => 'https://hooks.zapier.com/hooks/catch/10540636/2gyrtr0',
            'target' => 'hidden_iframe_9765',
            'checklist_name' => 'AE Handoff Checklist',
            'require_name' => true,
        ],
    ],
    [
        'kind' => 'checklist',
        'title' => 'VA Management',
        'blocks' => [
            [
                'kind' => 'text',
                'name' => 'hiringManagerName',
                'label' => 'Hiring Manager Name:',
                'placeholder' => 'Enter name...',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Blacklist',
            ],
            [
                'kind' => 'note',
                'depth' => 0,
                'html' => '<em>You&#39;re allowed to blacklist a VA if you feel that they are not someone we should present to clients, are unprofessional during interviews, or any reason you deem fit, really. You do not need permission.</em>',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'bl_item1',
                'html' => 'Open the VA&#39;s profile in RecruitCRM',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'bl_item2',
                'html' => 'Add a note to the profile explaining why you&#39;re blacklisting the VA; add the date in mm/dd Initials format <em>i.e. 04/02 VI &#45; Rude during interviews</em>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'bl_item3',
                'html' => 'Change <strong>Candidate Stage</strong> to <strong>Disqualified: Check Notes</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'bl_item4',
                'html' => 'Leave a message in #operations informing the team and hyperlink the name of the VA to the profile',
                'value' => 'checked',
            ],
            [
                'kind' => 'note',
                'depth' => 1,
                'html' => '🍖 <a href="https://app.recruitcrm.io/v1/candidate/2717295508181050072817nXZ" target="_blank" rel="noopener">Miguel Quintero</a> Blacklisted. Guy&#39;s a pompous asshole',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Fired',
            ],
            [
                'kind' => 'note',
                'depth' => 0,
                'html' => '<em>This is to be done when a client reaches out looking for a replacement because the VA has been let go.</em>',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'fi_item1',
                'html' => 'Open the VA&#39;s profile in RecruitCRM',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'fi_item2',
                'html' => 'Add a note to the profile explaining what happened; add the date in mm/dd Initials format <em>i.e. 04/02 VI &#45; Fired by Adam Aabel</em>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'fi_item3',
                'html' => 'Change <strong>Candidate Stage</strong> to <strong>Fired</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Reinstate into the pool',
            ],
            [
                'kind' => 'note',
                'depth' => 0,
                'html' => '<em>This is to be done ONLY and IF the client they were working with recommends the VA after they&#39;ve either quit or were let go.</em>',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ri_item1',
                'html' => 'Open the VA&#39;s profile in RecruitCRM',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ri_item2',
                'html' => 'Add a note to the profile explaining why you&#39;re reinstating the VA; add the date in mm/dd Initials format <em>i.e. 04/02 VI &#45; Was let go by Nick Kristiansen. Added back to the pool at client&#39;s recommendation</em>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ri_item3',
                'html' => 'Change <strong>Candidate Stage</strong> to <strong>Phase 3: Passed</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ri_item4',
                'html' => 'Remove &quot;Hired:&quot; from the VA&#39;s name',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ri_item5',
                'html' => 'Leave a message in #operations informing the team and hyperlink the name of the VA to the profile',
                'value' => 'checked',
            ],
            [
                'kind' => 'note',
                'depth' => 1,
                'html' => '🍖 <a href="https://app.recruitcrm.io/v1/candidate/17413758860960072817VYI" target="_blank" rel="noopener">Abraham Covarrubias</a> Fired by Nick Kristiansen, but recommended. Reinstated into the pool',
            ],
        ],
        'form' => [
            'id' => 'checklist-form-3461',
            'action' => 'https://hooks.zapier.com/hooks/catch/10540636/2gyrtr0',
            'target' => 'hidden_iframe_3461',
            'checklist_name' => 'VA Management Checklist',
            'require_name' => true,
        ],
    ],
    [
        'kind' => 'heading',
        'text' => 'FAQ/Objection Handlers',
    ],
    [
        'kind' => 'faq',
        'title' => 'VAs didn&#39;t show up to interview',
        'body' => '<p>I was expecting to get <strong>6</strong> applicants today on the interview but some of them are in other interviews and will join us as soon as they’re done, and others ended up getting job offers from other clients already, but I have <strong>3</strong> applicants for you to interview right now.</p>',
    ],
    [
        'kind' => 'faq',
        'title' => 'Needs time to think about moving forward with a specific VA',
        'body' => '<p><strong>Create sense of urgency:</strong></p><p>We can definitely wait, but just to let you know I think “John Smith” has an interview later today/tomorrow… let me check</p><p>*Click around CRM*</p><p>Yea it looks like he has an interview later today with another client… if they offer him a job we will lose him…&nbsp;</p><p>I really like him because of&nbsp;<strong>xyz&nbsp;</strong>reasons (sell the candidate, but if they want to wait, let them)&nbsp;</p>',
    ],
    [
        'kind' => 'faq',
        'title' => 'Low VA salary (offering too low)',
        'body' => '<p>We can absolutely try to find someone at a lower rate, but here’s the risk we’ve seen: when a VA isn’t happy with the pay, they often keep looking for other jobs while working for you. Even if they accept the offer now, they might leave after a few months — and you’re stuck retraining someone new all over again.</p><p>Offering a competitive rate helps with retention and lets us bring you stronger candidates who <em>actually</em> want the role — not just the ones settling because they don’t have other options.</p><p>Here’s what I’ll do: I’ll tell the recruiters to focus on high-quality candidates. If they happen to find someone great at a lower rate, we’ll bring them in. But I’ll have them keep the budget slightly open so we can get the best people for this role — ones who will stick around long-term.</p>',
    ],
    [
        'kind' => 'faq',
        'title' => 'Industry experience',
        'body' => '<p>From my experience and based on what I’ve seen from most of our other clients, they really don’t focus as much on industry experience as much as they focus on Skills.&nbsp;<br><br>If someone has the skills you need, you can always teach them all the industry knowledge and terms they need to get the job done in less than a week… it’s not that difficult to teach them about the industry as long as they have the skills you need.&nbsp;</p>',
    ],
    [
        'kind' => 'faq',
        'title' => 'Client requesting resumes',
        'body' => '<p>Happy to send resumes. That said, we typically send them the day of the interview or shortly before, since we need to first confirm final attendees. Candidates move quickly and assignments can change, so sending resumes too early often results in reviewing profiles of applicants who may no longer be part of the interview.</p>',
    ],
    [
        'kind' => 'faq',
        'title' => 'Tax filing',
        'body' => '<p>Here’s how we handle it on our end when it comes to taxes:</p><p>Any payments to Virtual Assistants are written off as contractor expenses when we file. If the VA is <em>not</em> a U.S. citizen, we don’t need to issue a 1099 or file anything with the IRS. But if they <em>are</em> a U.S. citizen, we do issue a 1099 like we would for any other domestic contractor.</p><p>Everyone we hire is brought on as an independent contractor, so they handle their own taxes in whatever country they live in.</p>',
    ],
    [
        'kind' => 'faq',
        'title' => 'Background checks',
        'body' => '<p>When you’re hiring outside the U.S., most people are brought on as independent contractors — so background checks usually don’t return much. It’s not like running one for a U.S.-based hire.</p><p>Some of our clients use a service like Checkr, which runs international background checks for around $30. But in our experience, the reports are usually pretty limited and not as helpful as you’d expect.</p><p>Instead, we take a more hands-on approach. We review their social media, LinkedIn, resume, and overall online presence to look for any red flags. And when we interview them, we dig into their background and past experience — which gives us a much better sense of their actual skill level and fit for the role.</p>',
    ],
    [
        'kind' => 'faq',
        'title' => 'Personality tests',
        'body' => '<p>We’ve found that personality tests aren’t super helpful anymore — mostly because candidates can easily game the system using AI tools to give ideal responses.</p><p>That said, if you’re a fan of them and want to include one, no problem at all — we can have the VA take it right after your interview.</p><p><strong>Note to HM:</strong> Create a Gmail account and give the VA your OpenPhone number to put in the test if it asks for personal details, and then share the results with the client this way so the client doesn’t get their direct info prior to payment.&nbsp;</p>',
    ],
    [
        'kind' => 'faq',
        'title' => 'Typing Test',
        'body' => '<p>We don’t typically require a typing test unless it’s a key part of the role, but we can easily have the VA take one if you’d like. Just let us know and we’ll get it done before you decide on who to hire.</p><p>*Note to HM: Have the applicant go to <a href="https://remoteleverage.com/typing" target="_blank" rel="noopener">remoteleverage.com/typing</a> and take a screenshot of their result and send it to you!</p>',
    ],
    [
        'kind' => 'faq',
        'title' => 'How to pay VAs',
        'body' => '<p>Very easy, we recommend using Wise or Western Union. We actually have a whole guide on that on our onboarding guide, let me have you go through these videos when you have the time and it’ll answer most of your questions about payroll, compliance, etc.&nbsp;</p><p>&nbsp;</p><p>Here is the link: <a href="https://remoteleverage.com/vaonboardingguide" target="_blank" rel="noopener">https://remoteleverage.com/vaonboardingguide</a></p>',
    ],
    [
        'kind' => 'faq',
        'title' => 'Requirements too specific',
        'body' => '<p>We can add in requirements like that, the problem is it removes a huge percentage of our applicants that would otherwise qualify for this job.</p><p>&nbsp;</p><p>So imagine instead of having 100 applicants for us to choose from for you to interview and choose the best for this job, we get stuck having to choose out of a small pool of 5 people or 10 people, way less than normal, because this requirement might take out most of our applicants from being considered, and so the overall skill and quality of the applicants we can get you is lower than it should usually be. My recommendation is we keep it as wide as possible so that we get the best people for you to hire, and then you can always teach them minor things that you want them to learn very quickly if they’re the right fit.</p>',
    ],
    [
        'kind' => 'faq',
        'title' => 'What if my VA quits?',
        'body' => '<p>We will replace them as many times as needed for 6 months to make sure they’re a perfect fit.&nbsp;</p>',
    ],
    [
        'kind' => 'faq',
        'title' => 'HMs: Modifications you can make to get a client to move forward',
        'body' => '<p>Don’t give these out too easily as we always want to sell our standard package which is:<br>40% Hiring Fee, Paid in Full&nbsp;</p><p>6 Month Replacement Guarantee</p><p>If you encounter issues, figure out why the client isn’t moving forward, and feel free to use these to get them to purchase:</p><p><strong>Worried about 6 month guarantee:</strong> Explain that’s our standard guarantee, but it’s extremely rare for anyone to need to replace someone after 2 months, as by then you’d know for sure whether they’re a good fit or not.&nbsp;</p><p>If this is still an issue and it’s a potential deal-breaker, extend to 12-month guarantee and send the 12-month agreement (there is one for 40% and another for 35%, choose the appropriate one, and if the client already paid a deposit, use the ones that have the words DEPOSIT PAID in the title).</p><p><strong>Pricing is too high:&nbsp;</strong>Try not to give a discount, but if you have to, drop it to 35%.</p><p><strong>One time payment is a big hurdle:&nbsp;</strong>Do a split payment; half now, half in a month.</p><p><strong>Pricing is too high AND one-time payment are both big issues:</strong> Do a split payment with a 35% fee. NOTE: this should be very rarely used, only if you try other methods and the client isn’t moving forward.&nbsp;</p><p><strong>Fighting Credit Card Fee: </strong>Explain that they can pay via ACH without any Credit Card fees, as the CC fee is what we pay for processing so it’s not a charge that goes to us, and most clients pay ACH to avoid paying it.&nbsp;</p><p>If they insist and it’s a deal breaker, remove the Credit Card fee.&nbsp;</p><p><strong>Payment too big even with 35%: </strong>If the VA is over $12/hr, see if you can get permission from Head of Hiring Managers to cap the cost at a certain dollar amount.&nbsp;</p>',
    ],
    [
        'kind' => 'faq',
        'title' => 'Compliance (Independent Contractor Vs. Employee)',
        'body' => '<p><b>1. Independent Contractor (Most Common)</b><br>&nbsp;This is how we structure things internally at Remote Leverage.<br>We pay our VAs directly as an independent contractor in their home country. They are responsible for their own local taxes, and from a U.S. standpoint, our CPA categorizes those payments as contractor expenses. Because the VA is not a U.S. citizen or U.S.-based worker, there is typically no requirement to issue a 1099 or file anything with the IRS.<br><br><b>2. Contractor of Record (We Handle Compliance)</b><br>&nbsp;If you’d rather not deal with the compliance side at all, we also offer a Contractor of Record option.<br>In this setup, the VA is technically contracted through Remote Leverage, but they work full-time with your business. We handle the contractor agreement, compliance, and administrative side of things. From your perspective, you simply manage the VA day-to-day while we handle the legal structure behind the scenes.<br>This option is popular for clients who want the simplicity of hiring internationally without worrying about contracts, classification, or compliance.</p>',
    ],
];
?>
<!-- wp:html -->
<section class="w-full bg-bg-light py-10 sm:py-14">
    <div class="<?= esc_attr($wrap) ?>">
        <header class="mb-8">
            <h1 class="font-display text-[26px] font-bold leading-8 tracking-[-0.6px] text-brand-navy sm:text-[34px] sm:leading-10">
                <?= wp_kses_post($h1) ?>
            </h1>
<?php if ($warning !== '') { ?>
            <h2 class="mt-3 rounded-md border border-status-alert/40 bg-status-warning/15 px-4 py-3 text-[17px] font-semibold leading-6 text-brand-navy sm:text-[19px]">
                <?= wp_kses_post($warning) ?>
            </h2>
<?php } ?>
        </header>

<?php
// @bespoke: internal ops tool, not a marketing section. Checked docs/block-inventory.md end
// to end — accordion-faq is the only block that renders disclosure rows, and it emits
// Schema.org FAQPage markup around static Q&A with no form, no fields and no submit. No
// block in the theme renders a webhook-backed checklist form, and adding one would mean a
// repeater deep enough to carry nested checkbox trees, inline notes and email templates for
// three pages that are staff-only. Field names and actions below are production's verbatim.
foreach ($nodes as $node) {
    if ($node['kind'] === 'heading') { ?>
        <h2 class="mt-10 mb-4 font-display text-[20px] font-semibold leading-7 text-brand-navy sm:text-[24px]">
            <?= wp_kses_post($node['text']) ?>
        </h2>
<?php
        continue;
    }

    if ($node['kind'] === 'faq') { ?>
        <details class="mb-2.5 overflow-hidden rounded-lg border border-black/10 bg-surface-white">
            <summary class="flex cursor-pointer list-none items-start justify-between gap-3 px-4 py-3 text-[15px] font-semibold leading-6 text-brand-navy [overflow-wrap:anywhere] [&::-webkit-details-marker]:hidden">
                <span><?= wp_kses_post($node['title']) ?></span>
                <span aria-hidden="true" class="shrink-0 text-[12px] text-brand-purple">&#9660;</span>
            </summary>
            <div class="space-y-3 border-t border-black/10 px-4 py-4 text-[15px] leading-7 [overflow-wrap:anywhere] [&_a]:text-brand-purple [&_a]:underline">
                <?= wp_kses_post($node['body']) ?>
            </div>
        </details>
<?php
        continue;
    }

    $form = $node['form'] ?? null;
    $fid = $form['id'] ?? 'ops-'.sanitize_title($node['title']);
    ?>
        <details class="mb-2.5 overflow-hidden rounded-lg border border-black/10 bg-surface-white">
            <summary class="flex cursor-pointer list-none items-start justify-between gap-3 bg-brand-navy px-4 py-3 text-surface-white [&::-webkit-details-marker]:hidden">
                <span class="min-w-0">
                    <span class="block text-[15px] font-semibold leading-6 [overflow-wrap:anywhere]"><?= wp_kses_post($node['title']) ?></span>
<?php if (($node['subtitle'] ?? '') !== '') { ?>
                    <span class="mt-1 block text-[13px] font-normal italic leading-5 text-surface-white/80 [overflow-wrap:anywhere]"><?= wp_kses_post($node['subtitle']) ?></span>
<?php } ?>
                </span>
                <span aria-hidden="true" class="shrink-0 text-[12px]">&#9660;</span>
            </summary>
            <div class="border-t border-black/10 px-4 py-5 sm:px-6">
<?php if ($form) { ?>
                <form id="<?= esc_attr($fid) ?>" action="<?= esc_url($form['action']) ?>" method="POST" target="<?= esc_attr($form['target']) ?>" data-ops-checklist<?= ! empty($form['require_name']) ? ' data-ops-require-name' : '' ?><?= isset($form['upsell_action']) ? ' data-ops-upsell-action="'.esc_url($form['upsell_action']).'"' : '' ?>>
                    <input type="hidden" name="checklistName" value="<?= esc_attr($form['checklist_name']) ?>">
<?php } ?>
<?php
    foreach ($node['blocks'] as $block) {
        $pad = $indent[$block['depth'] ?? 0] ?? '';
        $id = esc_attr($fid.'-'.($block['name'] ?? $block['id'] ?? ''));

        switch ($block['kind']) {
            case 'group': ?>
                    <p class="mt-5 mb-2 flex items-start gap-2 border-b border-black/10 pb-2 text-[15px] font-semibold leading-6 text-brand-navy <?= esc_attr($pad) ?>">
<?php if (($block['emoji'] ?? '') !== '') { ?>
                        <span aria-hidden="true"><?= esc_html($block['emoji']) ?></span>
<?php } ?>
                        <span><?= wp_kses_post($block['title']) ?></span>
                    </p>
<?php
                break;

            case 'item': ?>
                    <label for="<?= $id ?>" class="mb-1.5 flex cursor-pointer items-start gap-3 rounded-md p-2 text-[15px] leading-6 [overflow-wrap:anywhere] <?= esc_attr($tones[$block['tone'] ?? ''] ?? $tones['']) ?> <?= esc_attr($pad) ?>">
                        <input type="checkbox" id="<?= $id ?>" name="<?= esc_attr($block['name']) ?>" value="<?= esc_attr($block['value'] ?? 'checked') ?>" class="mt-1 size-[18px] shrink-0 cursor-pointer accent-brand-purple">
                        <span class="[&_a]:text-brand-purple [&_a]:underline"><?= wp_kses_post($block['html'] ?? '') ?></span>
                    </label>
<?php
                break;

            case 'note': ?>
                    <div class="mb-2 rounded-md border border-black/10 bg-bg-light px-3 py-2 text-[14px] leading-6 [overflow-wrap:anywhere] [&_a]:text-brand-purple [&_a]:underline <?= esc_attr($pad) ?>">
                        <?= wp_kses_post($block['html'] ?? '') ?>
                    </div>
<?php
                break;

            case 'template': ?>
                    <details class="mb-3 rounded-md border border-black/10 bg-bg-light <?= esc_attr($pad) ?>">
                        <summary class="cursor-pointer list-none px-3 py-2 text-[14px] font-semibold text-brand-purple-deep [&::-webkit-details-marker]:hidden">(click to expand)</summary>
                        <div class="space-y-2.5 border-t border-black/10 px-3 py-3 text-[14px] leading-6 [overflow-wrap:anywhere] [&_a]:text-brand-purple [&_a]:underline">
                            <?= wp_kses_post($block['html'] ?? '') ?>
                        </div>
                    </details>
<?php
                break;

            case 'text': ?>
                    <div class="mb-4 <?= esc_attr($pad) ?>">
                        <label for="<?= $id ?>" class="mb-1.5 block text-[14px] font-semibold leading-5 text-brand-navy"><?= wp_kses_post($block['label'] ?? '') ?></label>
                        <input type="text" id="<?= $id ?>" name="<?= esc_attr($block['name']) ?>" placeholder="<?= esc_attr($block['placeholder'] ?? '') ?>"<?= ! empty($block['required']) ? ' required' : '' ?> class="w-full rounded-md border border-black/15 bg-surface-white px-3 py-2.5 text-[15px] leading-6 text-black focus:border-brand-purple focus:outline-none">
                    </div>
<?php
                break;

            case 'textarea': ?>
                    <div class="mb-4 <?= esc_attr($pad) ?>">
<?php if (($block['label'] ?? '') !== '') { ?>
                        <label for="<?= $id ?>" class="mb-1.5 block text-[14px] font-semibold leading-5 text-brand-navy"><?= wp_kses_post($block['label']) ?></label>
<?php } ?>
                        <textarea id="<?= $id ?>" name="<?= esc_attr($block['name']) ?>" rows="<?= esc_attr($block['rows'] ?? 6) ?>" placeholder="<?= esc_attr($block['placeholder'] ?? '') ?>"<?= ! empty($block['required']) ? ' required' : '' ?> class="w-full resize-y rounded-md border border-black/15 bg-surface-white px-3 py-2.5 font-mono text-[14px] leading-6 text-black focus:border-brand-purple focus:outline-none<?= empty($block['rows']) ? ' min-h-[420px]' : '' ?>"><?= esc_textarea($block['value'] ?? '') ?></textarea>
                    </div>
<?php
                break;

            case 'select': ?>
                    <div class="mb-4 <?= esc_attr($pad) ?>">
<?php if (($block['label'] ?? '') !== '') { ?>
                        <label for="<?= $id ?>" class="mb-1.5 block text-[14px] font-semibold leading-5 text-brand-navy"><?= wp_kses_post($block['label']) ?></label>
<?php } ?>
                        <select id="<?= $id ?>"<?= isset($block['name']) ? ' name="'.esc_attr($block['name']).'"' : '' ?> data-ops-hm-jobs class="w-full rounded-md border border-black/15 bg-surface-white px-3 py-2.5 text-[15px] leading-6 text-black focus:border-brand-purple focus:outline-none">
<?php foreach ($block['options'] as $option) { ?>
                            <option value="<?= esc_attr($option['value']) ?>"><?= wp_kses_post($option['text']) ?></option>
<?php } ?>
                        </select>
                        <p data-ops-hm-jobs-status class="mt-2 min-h-[20px] text-[13px] leading-5 text-black/60" role="status" aria-live="polite"></p>
                    </div>
<?php
                break;

            case 'slackButton': ?>
                    <div class="mb-4 flex flex-wrap items-center gap-3 <?= esc_attr($pad) ?>">
                        <button type="button" data-ops-slack-todo class="rounded-md bg-brand-navy px-4 py-2.5 text-[14px] font-semibold text-surface-white hover:bg-brand-purple-deep">
                            <?= esc_html($block['label']) ?>
                        </button>
                        <span data-ops-slack-status class="text-[13px] leading-5 text-black/60" role="status" aria-live="polite"></span>
                    </div>
<?php
                break;
        }
    }
    ?>
<?php if ($form) { ?>
                    <div class="mt-5 flex flex-wrap items-center gap-3">
                        <button type="submit" class="rounded-md bg-brand-purple px-5 py-2.5 text-[15px] font-semibold text-surface-white hover:bg-brand-purple-deep">
                            Submit Checklist
                        </button>
                        <span data-ops-status class="text-[14px] leading-6 font-medium" role="status" aria-live="polite"></span>
                    </div>
                </form>
                <iframe name="<?= esc_attr($form['target']) ?>" title="<?= esc_attr($node['title']) ?> response" tabindex="-1" aria-hidden="true" class="hidden h-0 w-0 border-0"></iframe>
<?php } ?>
            </div>
        </details>
<?php } ?>
    </div>
</section>
<script>
(function () {
    var NOTE = 'text-status-alert';
    var OK = 'text-brand-purple-deep';

    function say(form, message, ok) {
        var el = form.querySelector('[data-ops-status]');
        if (!el) { return; }
        el.textContent = message;
        el.className = 'text-[14px] leading-6 font-medium ' + (ok ? OK : NOTE);
    }

    document.querySelectorAll('[data-ops-checklist]').forEach(function (form) {
        var sent = false;

        form.addEventListener('submit', function (event) {
            var boxes = Array.prototype.slice.call(form.querySelectorAll('input[type="checkbox"]'));
            var named = form.querySelector('input[type="text"]');

            if (form.hasAttribute('data-ops-require-name') && named && !named.value.trim()) {
                event.preventDefault();
                say(form, 'Please enter your name before submitting.', false);
                return;
            }

            if (boxes.length && !boxes.every(function (box) { return box.checked; })) {
                event.preventDefault();
                say(form, 'Please check all boxes before submitting.', false);
                return;
            }

            var upsell = form.getAttribute('data-ops-upsell-action');

            if (upsell) {
                var extra = Array.prototype.slice.call(form.querySelectorAll('[name^="upsell_"]'));

                if (extra.some(function (field) { return !field.value.trim(); })) {
                    event.preventDefault();
                    say(form, 'Please fill out all Upsell Inquiry fields before submitting.', false);
                    return;
                }

                // Production posts the upsell answers to a second Zapier hook under their own
                // short field names, alongside the checklist POST. Keep both.
                var payload = new FormData();
                payload.append('hiringManagerName', named ? named.value.trim() : '');
                extra.forEach(function (field) {
                    payload.append(field.name.replace(/^upsell_/, ''), field.value.trim());
                });
                fetch(upsell, { method: 'POST', body: payload, mode: 'no-cors' });
            }

            sent = true;
            say(form, '', true);
        });

        var frame = document.getElementsByName(form.getAttribute('target'))[0];

        if (frame) {
            frame.addEventListener('load', function () {
                if (!sent) { return; }
                sent = false;
                say(form, 'Checklist submitted successfully!', true);
                form.reset();
            });
        }
    });
})();
</script>
<script>
(function () {
    var JOBS_HOOK = 'https://n8n.srv1338052.hstgr.cloud/webhook/hm/jobs';
    var SLACK_HOOK = 'https://n8n.srv1338052.hstgr.cloud/webhook/send-todo-to-slack';
    var STATUS_ORDER = [
        'Onboarding not booked',
        'Onboarding Booked',
        'Missed Onboarding',
        'Hiring Form Submitted',
        'Interview Scheduled',
        'Client Missed Interview',
        'Second Round of Interviews',
        'VA Presentation Scheduled'
    ];

    var select = document.querySelector('[data-ops-hm-jobs]');
    var jobsStatus = document.querySelector('[data-ops-hm-jobs-status]');
    var todo = document.querySelector('textarea[name="dailyTeamUpdate"]');
    var slackButton = document.querySelector('[data-ops-slack-todo]');
    var slackStatus = document.querySelector('[data-ops-slack-status]');

    function format(categories) {
        if (!categories || !categories.length) {
            return 'No active jobs found for this hiring manager.';
        }

        var ordered = STATUS_ORDER.map(function (status) {
            return categories.find(function (category) { return category.status === status; });
        }).filter(Boolean);

        var rest = categories.filter(function (category) {
            return STATUS_ORDER.indexOf(category.status) === -1;
        });

        return ordered.concat(rest).map(function (category) {
            return category.status + '\n' + category.jobs.map(function (job) { return '* ' + job + ':'; }).join('\n');
        }).join('\n\n');
    }

    if (select && todo) {
        select.addEventListener('change', function () {
            var name = select.value;

            if (!name) { jobsStatus.textContent = ''; return; }

            jobsStatus.textContent = 'Loading jobs...';
            fetch(JOBS_HOOK, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name: name })
            }).then(function (response) {
                if (!response.ok) { throw new Error('Server responded with ' + response.status); }
                return response.json();
            }).then(function (data) {
                var payload = Array.isArray(data) ? data[0] : data;
                todo.value = format(payload.categories || payload);
                jobsStatus.textContent = (payload.total_jobs === undefined ? '?' : payload.total_jobs) + ' job(s) loaded for ' + name;
            }).catch(function (error) {
                jobsStatus.textContent = 'Error: ' + error.message;
            });
        });
    }

    if (slackButton && todo) {
        slackButton.addEventListener('click', function () {
            var text = todo.value;
            var name = select ? select.value : '';

            if (!text.trim()) {
                slackStatus.textContent = 'Nothing to send \u2014 textarea is empty.';
                return;
            }

            slackStatus.textContent = 'Sending...';
            fetch(SLACK_HOOK, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    todo: name ? '*Hiring Manager:* ' + name + '\n\n' + text : text,
                    hiringManager: name,
                    submittedAt: new Date().toISOString()
                })
            }).then(function (response) {
                if (!response.ok) { throw new Error('HTTP ' + response.status); }
                slackStatus.textContent = 'Sent to #operations \u2713';
            }).catch(function (error) {
                slackStatus.textContent = 'Failed: ' + error.message;
            });
        });
    }
})();
</script>
<!-- /wp:html -->
