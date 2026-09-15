<?php

/**
 * Title: Full Page - Recruiter Checklists
 * Slug: remote-leverage/recruiterchecklists
 * Categories: remote-leverage
 * Description: Production's /recruiterchecklists/ internal ops tool — the seven recruiter checklists, posting to the same n8n webhook.
 */

/*
 * Internal ops tool, migrated from production /recruiterchecklists/ on 2026-09-15. Staff use these
 * daily, so every form below keeps production's action, method, target and field names
 * byte for byte — a renamed input silently breaks the downstream automation. Checklist
 * copy is production's verbatim; only the presentation is re-cut onto theme tokens.
 *
 * Webhook: https://n8n.srv1338052.hstgr.cloud/webhook/recruiters-checklist
 */

$wrap = 'w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8';
$h1 = 'Recruiter Checklists';
$warning = '';

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
        'kind' => 'checklist',
        'title' => '✔️ Vetting Checklist',
        'blocks' => [
            [
                'kind' => 'text',
                'name' => 'recruiterName',
                'label' => '👤 Recruiter Name:',
                'placeholder' => 'Enter name...',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Vetting Process Steps',
                'emoji' => '🔍',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'vet_item1',
                'html' => 'Check all applicants on page (25 per page)',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'vet_item2',
                'tone' => 'success',
                'html' => '✅ If someone passed, uncheck them from vetting page',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'vet_item3',
                'html' => 'Open up their profile',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'vet_item4',
                'html' => 'Change Stage to <strong>Passed: Phase 1</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'vet_item5',
                'html' => 'Send Email Invite to Interview',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'vet_item6',
                'html' => 'Continue on with rest of page, repeat',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'vet_item7',
                'tone' => 'danger',
                'html' => '❌ Once done with page, update the checked ones to Candidate Stage: Failed',
                'value' => 'checked',
            ],
        ],
        'form' => [
            'id' => 'checklist-form-1',
            'action' => 'https://n8n.srv1338052.hstgr.cloud/webhook/recruiters-checklist',
            'target' => 'hidden_iframe_1',
            'checklist_name' => 'Vetting Checklist',
            'require_name' => false,
        ],
    ],
    [
        'kind' => 'checklist',
        'title' => '🎤 Interviews Checklist',
        'blocks' => [
            [
                'kind' => 'text',
                'name' => 'recruiterName',
                'label' => '👤 Recruiter Name:',
                'placeholder' => 'Enter name...',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Initial Assessment',
                'emoji' => '📋',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'interviews_item1',
                'html' => 'See if they qualify for a different role than what they apply for',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'If Failed',
                'emoji' => '❌',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'interviews_item2',
                'tone' => 'danger',
                'html' => 'Change Stage to Failed Interview',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'If Passed',
                'emoji' => '✅',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'interviews_item3',
                'html' => 'Fluent English (if needed for the job)',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'interviews_item4',
                'html' => 'Experience as a VA / Work from home 6+ Months',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'interviews_item5',
                'html' => '6+ month experience in whatever job they&#39;re applying for',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'interviews_item6',
                'html' => '2+ Years for non voice jobs &#45; Marketing, accounting, graphic/video, web design etc',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'interviews_item7',
                'html' => 'Majority of job experience matches role(s) they&#39;re applying for (which may be different to what they applied for)',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'interviews_item8',
                'html' => 'Can expand for 2-3 mins on experience on their resume',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'interviews_item9',
                'html' => 'Got a portfolio link? place in the notes of the VA',
                'value' => 'checked',
            ],
            [
                'kind' => 'note',
                'depth' => 1,
                'tone' => 'success',
                'html' => '🎯 Final Steps:',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'interviews_item10',
                'tone' => 'success',
                'html' => 'Change Stage to Phase 3: Passed',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'interviews_item11',
                'html' => 'Change Positions Approved to',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'interviews_item12',
                'html' => 'Send Skills Assessment (After Phase 3) Email',
                'value' => 'checked',
            ],
        ],
        'form' => [
            'id' => 'checklist-form-2',
            'action' => 'https://n8n.srv1338052.hstgr.cloud/webhook/recruiters-checklist',
            'target' => 'hidden_iframe_2',
            'checklist_name' => 'Interviews Checklist',
            'require_name' => false,
        ],
    ],
    [
        'kind' => 'checklist',
        'title' => '🎫 Client Ticket Vetting Checklist',
        'blocks' => [
            [
                'kind' => 'text',
                'name' => 'recruiterName',
                'label' => '👤 Recruiter Name:',
                'placeholder' => 'Enter name...',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Initial Assessment',
                'emoji' => '📖',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ctv_item1',
                'html' => 'Read and understand the Client Ticket on Jobs thoroughly',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ctv_item1a',
                'html' => 'Find the onboarding call/interview recording on Zoom and skim through it to understand what the Client is looking for',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ctv_item2',
                'html' => 'Note key requirements (role, experience, skills, location preferences)',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Candidate Search Setup',
                'emoji' => '🔍',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ctv_item3',
                'html' => 'Open a new tab in Recruit CRM',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ctv_item4',
                'html' => 'Set filters:',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'ctv_item4a',
                'html' => 'Role (Admin, Sales, etc as specified)',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'ctv_item4b',
                'html' => 'Candidate Stage <strong>&quot;Phase 3 Passed&quot;</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'ctv_item4c',
                'html' => 'Country (Not Contains Philippines, or as specified)',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'ctv_item4d',
                'html' => 'Boolean search for specific keywords related to required work experience (Healthcare, SEO, etc)',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Candidate Evaluation',
                'emoji' => '📊',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ctv_item5',
                'html' => 'Open candidate profile that matches initial filter',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ctv_item6',
                'html' => 'Review resume thoroughly to fit with client requirements',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ctv_item7',
                'html' => 'Listen to voice recording',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ctv_item8',
                'tone' => 'warning',
                'html' => '⚠️ Check assigned jobs to ensure candidate doesn&#39;t have more than 5 MAX ACTIVE jobs already assigned',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Final Selection',
                'emoji' => '✅',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ctv_item9',
                'html' => 'If candidate meets all requirements: Copy the candidate&#39;s email address',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ctv_item10',
                'html' => 'Navigate to &quot;Assigning Candidate&quot; tab on the client ticket',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ctv_item11',
                'html' => 'Paste email address',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Post-Assignment',
                'emoji' => '🎯',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ctv_item12',
                'tone' => 'success',
                'html' => 'Change ticket stage to <strong>Candidate Vetting Completed</strong>',
                'value' => 'checked',
            ],
        ],
        'form' => [
            'id' => 'checklist-form-14',
            'action' => 'https://n8n.srv1338052.hstgr.cloud/webhook/recruiters-checklist',
            'target' => 'hidden_iframe_14',
            'checklist_name' => 'Client Ticket Vetting Checklist',
            'require_name' => false,
        ],
    ],
    [
        'kind' => 'checklist',
        'title' => '📅 Calendar Invite for VA Interviews',
        'blocks' => [
            [
                'kind' => 'text',
                'name' => 'recruiterName',
                'label' => '👤 Recruiter Name:',
                'placeholder' => 'Enter name...',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Initial Confirmation',
                'emoji' => '✅',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'cal_item1',
                'html' => 'Confirm Client interview is scheduled and Ticket is approved by Hiring Manager',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'VA Calendar Invite Creation',
                'emoji' => '📆',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'cal_item2',
                'html' => 'Schedule it 15 minutes prior to interview (for Hiring Manager briefing)',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'cal_item3',
                'html' => 'Set the name to &quot;VA Interview&quot; to distinguish it from client invite',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'cal_item4',
                'html' => 'Name the calendar: &quot;Remote Leverage &#45; Client Interview (name of client)&quot;',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Template and Description',
                'emoji' => '📝',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'cal_item5',
                'html' => 'Access the template from <a href="https://form.jotform.com/234865700500052" target="_blank" rel="noopener">Jotform</a>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'cal_item6',
                'html' => 'Fill out with correct client and job information',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'cal_item7',
                'html' => 'Copy and paste the template as the description of the calendar invite',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'cal_item7a',
                'html' => 'Read latest job notes to ensure the original Job Description template matches what the client wants. If it doesn&#39;t match due to client changes, use the job description generator tool to come up with a new job description to put on the calendar invitation and email',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Notification Settings',
                'emoji' => '🔔',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'cal_item8',
                'html' => 'Change notification method to email',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'cal_item9',
                'html' => 'Set notification times to 1 hour before, 10 minutes before, 0 minutes (time of event)',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Finalizing Calendar Invite',
                'emoji' => '✔️',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'cal_item10',
                'html' => 'Add all the VAs to the calendar invite',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'cal_item11',
                'html' => 'Confirm the calendar schedule',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Email Notification',
                'emoji' => '📧',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'cal_item11a',
                'tone' => 'warning',
                'html' => '⚠️ Call the VAs to make sure they got the invite, and if they don&#39;t answer, message them to let them know and ask them to accept the invitation',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'cal_item12',
                'html' => 'Email the VAs to inform them they&#39;ve been shortlisted',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'cal_item13',
                'html' => 'Use BCC for all VA email',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'cal_item14',
                'html' => 'Get the final interview format from Jotform for the email content',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'cal_item15',
                'html' => 'Send initial email',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Call &amp; Text',
                'emoji' => '📞',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'cal_item15_call',
                'html' => '<strong>Call</strong> the VA to let them know you sent them a job interview invitation and tell them to accept. If they don&#39;t answer, leave a voicemail if possible.',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'cal_item15_text',
                'html' => '<strong>Text</strong> the VA to let them know to accept the job invitation.',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Follow-up',
                'emoji' => '🔄',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'cal_item16',
                'tone' => 'success',
                'html' => '✅ If they don&#39;t accept the invitation within a day, replace them with other candidates',
                'value' => 'checked',
            ],
        ],
        'form' => [
            'id' => 'checklist-form-3',
            'action' => 'https://n8n.srv1338052.hstgr.cloud/webhook/recruiters-checklist',
            'target' => 'hidden_iframe_3',
            'checklist_name' => 'Calendar Invite for VA Interviews Checklist',
            'require_name' => false,
        ],
    ],
    [
        'kind' => 'checklist',
        'title' => '🔥 Firefighting Checklist',
        'blocks' => [
            [
                'kind' => 'text',
                'name' => 'recruiterName',
                'label' => '👤 Recruiter Name:',
                'placeholder' => 'Enter name...',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Claim the Ticket',
                'emoji' => '🎫',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ff_item1',
                'html' => 'Respond in the Firefighting chat to claim the firefighting ticket so other Senior Recruiters and the Hiring Manager know you&#39;re handling it',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Candidate Handling &amp; Approvals',
                'emoji' => '👥',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ff_item2',
                'html' => 'Unless it&#39;s an immediate interview, if you add any VAs to the ticket, update the NOTES and message the Hiring Manager',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ff_item3',
                'html' => 'If the interview is for a future time, message the HM for a quick review of the candidates if they&#39;re available to approve. If they&#39;re not available or no response within a couple of mins, assume candidates are approved',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ff_item3a',
                'html' => 'If the Hiring Manager refuses any VAs, replace with new candidates and message the Hiring Manager again',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Outreach &amp; Scheduling',
                'emoji' => '📞',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ff_item4',
                'html' => 'Send out calendar invites',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ff_item5',
                'html' => 'Send confirmation emails',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ff_item6',
                'html' => 'Send SMS/texts as needed',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ff_item7',
                'html' => 'Make calls as needed to confirm attendance',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Point of Contact',
                'emoji' => '🎯',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ff_item8',
                'html' => 'You are the recruiting Point of Contact for the ticket going forward unless it gets sent back to &quot;Hiring Form Submitted&quot;',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Closeout',
                'emoji' => '✅',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ff_item9',
                'html' => 'Once the fire is fully put out, comment &quot;Done &#45; CLIENT NAME HERE&quot; on the ticket thread',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'ff_item10',
                'tone' => 'success',
                'html' => '🗑️ Delete the ticket from the Firefighting Slack chat',
                'value' => 'checked',
            ],
        ],
        'form' => [
            'id' => 'checklist-form-ff',
            'action' => 'https://n8n.srv1338052.hstgr.cloud/webhook/recruiters-checklist',
            'target' => 'hidden_iframe_ff',
            'checklist_name' => 'Firefighting Checklist',
            'require_name' => false,
        ],
    ],
    [
        'kind' => 'checklist',
        'title' => '👔 Head of Recruiting Checklist',
        'blocks' => [
            [
                'kind' => 'text',
                'name' => 'recruiterName',
                'label' => '👤 Recruiter Name:',
                'placeholder' => 'Enter name...',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Daily Tasks',
                'emoji' => '📅',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'daily_updates',
                'html' => 'Provide daily updates: number of applicants, internal role hiring progress, performance of job platforms used',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'prioritize_postings',
                'html' => 'Change job postings on different platforms to prioritize the most in-demand applicants',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'maximize_slots',
                'html' => 'Ensure we are maximizing all slots on job platforms',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'review_mistakes',
                'html' => 'Add a note to review any mistakes from the previous day in the daily training',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'calendar_slots',
                'html' => 'Calendar booked out? Add more slots. If not enough recruiters, hire more to open up capacity',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'check_vetting',
                'html' => 'Check vetting queue to ensure we&#39;re not falling behind; if we are, accelerate work or hire more',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'email_missed',
                'html' => 'Ensure we&#39;re emailing applicants who missed their interviews',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Weekly Tasks',
                'emoji' => '📆',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'team_busyness',
                'html' => 'Ask the team how busy they are on a scale of 1–10; if average &gt; 7, hire more recruiters',
                'value' => 'checked',
            ],
        ],
        'form' => [
            'id' => 'checklist-form-4',
            'action' => 'https://n8n.srv1338052.hstgr.cloud/webhook/recruiters-checklist',
            'target' => 'hidden_iframe_4',
            'checklist_name' => 'Head of Recruiting Checklist',
            'require_name' => false,
        ],
    ],
    [
        'kind' => 'checklist',
        'title' => '👥 Internal Hiring Checklist',
        'blocks' => [
            [
                'kind' => 'text',
                'name' => 'recruiterName',
                'label' => '👤 Recruiter Name:',
                'placeholder' => 'Enter name...',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Candidate Confirmation',
                'emoji' => '✅',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'hire_item1',
                'html' => 'Contact candidates to inform them of the job offer and confirm their acceptance',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'hire_item2',
                'html' => 'After they confirm, inform the department head of the confirmation',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Explain Onboarding Process',
                'emoji' => '📋',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'hire_item3',
                'html' => 'Explain you will be sending a Work Offer, an Independent Contractor Agreement and a W9 (if they&#39;re a U.S. Citizen) or a W8-Ben',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'hire_item4',
                'html' => 'Explain Welcome Kit with invites to Slack and Time Doctor',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'hire_item5',
                'html' => 'Explain an invite to their onboarding on their start date',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'hire_item6',
                'html' => 'Confirm that they understand each part of the onboarding process',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Update RecruitCRM',
                'emoji' => '💼',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'hire_item7',
                'html' => 'Update RecruitCRM profile candidate stage to &quot;Placed&quot;',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'hire_item8',
                'html' => 'Leave a note &quot;Hired by Remote Leverage&quot;',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Send Jotform Documents',
                'emoji' => '📄',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'hire_item9',
                'html' => 'Send Work Offer &#45; Direct Staff',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'hire_item10',
                'html' => 'Send W9 (if they&#39;re a U.S. citizen) or W8 Ben',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'hire_item11',
                'html' => 'Send Independent Contractor Agreement',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Onboard to Internal Platforms',
                'emoji' => '🔧',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'hire_item12',
                'html' => 'Send invitation to Slack and grant channel access according to the department they&#39;ll be working for',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'hire_item13',
                'html' => 'Send invitation to Time Doctor',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'hire_item14',
                'html' => 'Change user name to First and Last Name',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'hire_item15',
                'html' => 'Ensure settings for Time Doctor are default (Time Out after 3 minutes, Screencasts every 3 minutes)',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Send Welcome Kit Email',
                'emoji' => '📧',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'hire_item16',
                'html' => 'Ensure you are entering their start date and start time on the welcome kit',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Create Onboarding Calendar Invite',
                'emoji' => '📅',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'hire_item17',
                'html' => 'Create a calendar event with the following format:<br> <strong>Title:</strong> Onboarding &#45; Remote Leverage 🎊',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'hire_item18',
                'html' => '<strong>Description:</strong> &quot;Welcome to Remote Leverage!<br><br> We&#39;re excited to have you join the team 🎉<br><br> Ahead of our onboarding session, please make sure you&#39;ve completed the following to ensure a smooth start:<br> &#45; Signed your work offer and other documents<br> &#45; Installed the Time Doctor App<br> &#45; Installed Slack (our main team communication tool)<br><br> Looking forward to meeting you and kicking things off!&quot;',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Update Internal Hires Tracker',
                'emoji' => '📊',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'hire_item19',
                'tone' => 'success',
                'html' => '✅ Update the <a href="https://docs.google.com/spreadsheets/d/1-N-eUIz_ed-dpCj1pUYR61BZ4UUw7lbiM0zliUOt2TM/edit?usp=sharing" target="_blank" rel="noopener">Internal Hires Tracker</a> with their information',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'On Start Date',
                'emoji' => '🎯',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'hire_item20',
                'html' => 'Go through our <a href="https://www.canva.com/design/DAGrB91003A/PrDBh_w8NIPdgxb3Fuy9Rw/edit" target="_blank" rel="noopener">New Hire Presentation</a>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'hire_item21',
                'html' => 'Ensure all documents are signed',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'hire_item22',
                'html' => 'And remind them to fill out the appropriate forms',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'hire_item23',
                'html' => 'Have them screenshare a speedtest. Requirements are at least 40mbps internet speed. If they do not meet the requirement, they cannot start and must contact their provider.',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'hire_item24',
                'html' => 'After the presentation, hand them off to their department head to begin training',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'For Offboarding',
                'emoji' => '👋',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'hire_item25',
                'html' => 'Confirm offboarding with their department head, if they have internal credentials, coordinate with the department head to submit an IT ticket for removal',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'hire_item26',
                'html' => 'Remove them from Slack and Time Doctor',
                'value' => 'checked',
            ],
        ],
        'form' => [
            'id' => 'checklist-form-342',
            'action' => 'https://n8n.srv1338052.hstgr.cloud/webhook/recruiters-checklist',
            'target' => 'hidden_iframe_342',
            'checklist_name' => 'Internal Hiring Checklist',
            'require_name' => false,
        ],
    ],
];
?>
<!-- rl:noindex-follow — internal ops tool, but production still passes link equity through. App\Support\PageRobots reads this marker and emits
     noindex, follow, matching production. The page stays published and reachable; this
     keeps it out of search results only, it is NOT access control. -->
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
<!-- /wp:html -->
