<?php

/**
 * Title: Full Page - Sales Checklists
 * Slug: remote-leverage/saleschecklists
 * Categories: remote-leverage
 * Description: Production's /saleschecklists/ internal ops tool — the ten sales checklists, posting to the same n8n webhook.
 */

/*
 * Internal ops tool, migrated from production /saleschecklists/ on 2026-09-15. Staff use these
 * daily, so every form below keeps production's action, method, target and field names
 * byte for byte — a renamed input silently breaks the downstream automation. Checklist
 * copy is production's verbatim; only the presentation is re-cut onto theme tokens.
 *
 * Webhook: https://n8n.srv1338052.hstgr.cloud/webhook/sales-checklists
 */

$wrap = 'w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8';
$h1 = 'Sales Checklists';
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
        'title' => 'New Appointment Checklist',
        'blocks' => [
            [
                'kind' => 'text',
                'name' => 'leadName',
                'label' => 'Lead Name:',
            ],
            [
                'kind' => 'text',
                'name' => 'salespersonName',
                'label' => 'Salesperson Name:',
            ],
            [
                'kind' => 'item',
                'depth' => 0,
                'name' => 'appt_item1',
                'html' => '<strong>Email bounced?</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'appt_item2',
                'html' => 'Verify name of the person is not misspelled in the email',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'appt_item3',
                'html' => 'Verify email domain is not misspelled (if it’s a custom business email domain, make sure the name of the business isn’t accidentally misspelled in the email domain –&nbsp;<a href="https://www.loom.com/share/43a27cacaf484efb828f5c7052b0f7f7" target="_blank" rel="noopener">Click here for video instructions</a>)',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'appt_item4',
                'html' => 'Correcting Mistakes: Add corrected email to the Google calendar invitation, forward the auto Virtual Assistant Hiring Consultation Agenda email to the correct email, update CRM Deal &amp; Contact with the correct email',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 0,
                'name' => 'appt_item5',
                'html' => '<strong>Lead doesn’t see calendar invitation</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'appt_item6',
                'html' => '“You might’ve put in an email that you’re not signed into your calendar with, what other email do you have that I can send the invitation to?”',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'appt_item7',
                'html' => 'Send invitation to the new email',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 0,
                'name' => 'appt_item8',
                'html' => '<strong>Lead sees invitation but doesn’t see email</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'appt_item9',
                'html' => 'Ask them to check spam or promotions, and if they don’t see it, ask for another email and forward to them then update CRM Contact &amp; Deal with the new email and add the new email to the Google invitation',
                'value' => 'checked',
            ],
        ],
        'form' => [
            'id' => 'checklist-form-new-1',
            'action' => 'https://n8n.srv1338052.hstgr.cloud/webhook/sales-checklists',
            'target' => 'hidden_iframe_new_1',
            'checklist_name' => 'New Appointment Checklist',
            'require_name' => false,
        ],
    ],
    [
        'kind' => 'checklist',
        'title' => 'Refund Requests Checklist',
        'blocks' => [
            [
                'kind' => 'text',
                'name' => 'leadName',
                'label' => 'Lead Name:',
            ],
            [
                'kind' => 'text',
                'name' => 'salespersonName',
                'label' => 'Salesperson Name:',
            ],
            [
                'kind' => 'item',
                'depth' => 0,
                'name' => 'refund_item1',
                'html' => '<strong>Try to save it (Call, Text, Email the client)</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 0,
                'name' => 'refund_item2',
                'html' => '<strong>Within 24 hours, if client doesn&#39;t respond:</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'refund_item3',
                'html' => 'Stripe: Find the payment on Stripe, open it and Click on Refund',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'refund_item4',
                'html' => 'Find the contact on the CRM and open it to find the deals and jobs tab',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'refund_item5',
                'html' => 'Deal tab: Change Deal status to Canceled (after signing up)',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'refund_item6',
                'html' => 'Job Tab: Change Job status to Canceled',
                'value' => 'checked',
            ],
        ],
        'form' => [
            'id' => 'checklist-form-refund-1',
            'action' => 'https://n8n.srv1338052.hstgr.cloud/webhook/sales-checklists',
            'target' => 'hidden_iframe_refund_1',
            'checklist_name' => 'Refund Requests Checklist',
            'require_name' => false,
        ],
    ],
    [
        'kind' => 'checklist',
        'title' => 'Deleting Virtual Assistant Appointments',
        'blocks' => [
            [
                'kind' => 'text',
                'name' => 'salespersonName',
                'label' => 'Salesperson Name:',
            ],
            [
                'kind' => 'item',
                'depth' => 0,
                'name' => 'sales_item1',
                'html' => 'Message Abbas the name and phone of the VA that booked the appointment',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 0,
                'name' => 'sales_item2',
                'html' => 'Cancel the appointment from your calendar (if haven’t met yet)',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 0,
                'name' => 'sales_item3',
                'html' => 'Delete Contact, Deal, and Company:',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'sales_item4',
                'html' => 'Find and click on the VA name on the CRM',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'sales_item5',
                'html' => 'Right-click on the company and <em>Open Link in New Tab</em>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'sales_item6',
                'html' => 'Click on the 3 dots and click on Delete',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'sales_item7',
                'html' => 'Back on the Contact card, click on Related Deals tab, and open the associated deal in new tab',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 2,
                'name' => 'sales_item8',
                'html' => 'Click on the 3 dots and click on Delete',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'sales_item9',
                'html' => 'Back to the contact card, click on the 3 dots and delete the actual contact',
                'value' => 'checked',
            ],
        ],
        'form' => [
            'id' => 'checklist-form-sales-1',
            'action' => 'https://n8n.srv1338052.hstgr.cloud/webhook/sales-checklists',
            'target' => 'hidden_iframe_sales_1',
            'checklist_name' => 'Deleting Virtual Assistant Appointments',
            'require_name' => false,
        ],
    ],
    [
        'kind' => 'checklist',
        'title' => 'Sold &#45; Deal Closed Checklist',
        'blocks' => [
            [
                'kind' => 'text',
                'name' => 'salespersonName',
                'label' => 'Salesperson Name:',
            ],
            [
                'kind' => 'item',
                'depth' => 0,
                'name' => 'sales_item10',
                'html' => 'Change Deal Status to <strong>Won</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 0,
                'name' => 'sales_item11',
                'html' => 'Change Close Date to today’s date',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 0,
                'name' => 'sales_item12',
                'html' => 'Fill out all the information field boxes on the deal including industry',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 0,
                'name' => 'sales_item17',
                'html' => 'Fireflies: Copy &amp; paste the Fireflies AI summary in both the contact and deal notes. Post these separately, then add more manual notes as per list below.',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 0,
                'name' => 'sales_item13',
                'html' => 'Update notes with any special changes needed for the client:',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'sales_item14',
                'html' => 'Discount applied/Split payment if any',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'sales_item15',
                'html' => 'Notes about the client and what they&#39;re looking for, both on the Deal and on the Contact',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'sales_item16',
                'html' => 'Other special terms discussed',
                'value' => 'checked',
            ],
        ],
        'form' => [
            'id' => 'checklist-form-sales-2',
            'action' => 'https://n8n.srv1338052.hstgr.cloud/webhook/sales-checklists',
            'target' => 'hidden_iframe_sales_2',
            'checklist_name' => 'Sold - Deal Closed Checklist',
            'require_name' => false,
        ],
    ],
    [
        'kind' => 'checklist',
        'title' => 'Met &#45; Post Meeting Checklist',
        'blocks' => [
            [
                'kind' => 'text',
                'name' => 'salespersonName',
                'label' => 'Salesperson Name:',
            ],
            [
                'kind' => 'item',
                'depth' => 0,
                'name' => 'sales_item17',
                'html' => 'Change Deal Stage to <strong>Met</strong> or <strong>Lost</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 0,
                'name' => 'sales_item18',
                'html' => 'Send Recap Email to the client',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 0,
                'name' => 'sales_item19',
                'html' => 'Update Notes in CRM',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 0,
                'name' => 'sales_item20',
                'html' => 'Fill out all information fields on the deal',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 0,
                'name' => 'sales_item21',
                'html' => 'If following up, schedule the next task:',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'sales_item22',
                'html' => 'Assign next follow-up task date in CRM',
                'value' => 'checked',
            ],
        ],
        'form' => [
            'id' => 'checklist-form-sales-3',
            'action' => 'https://n8n.srv1338052.hstgr.cloud/webhook/sales-checklists',
            'target' => 'hidden_iframe_sales_3',
            'checklist_name' => 'Met - Post Meeting Checklist',
            'require_name' => false,
        ],
    ],
    [
        'kind' => 'checklist',
        'title' => 'Missed Meeting &#45; Follow-Up Checklist',
        'blocks' => [
            [
                'kind' => 'text',
                'name' => 'salespersonName',
                'label' => 'Salesperson Name:',
            ],
            [
                'kind' => 'item',
                'depth' => 0,
                'name' => 'sales_item23',
                'html' => 'Call the prospect',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'sales_item24',
                'html' => 'If no pickup, hang up and dial again',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'sales_item25',
                'html' => 'If no pickup again, leave a voicemail',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 0,
                'name' => 'sales_item26',
                'html' => 'Send a follow-up text message',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 0,
                'name' => 'sales_item27',
                'html' => 'Stay on the call for a minimum of 10 minutes',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 0,
                'name' => 'sales_item28',
                'html' => 'Change deal stage to <strong>Missed Meeting</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 0,
                'name' => 'sales_item29',
                'html' => 'Send Missed Meeting Email',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 0,
                'name' => 'sales_item30',
                'html' => 'Schedule the next follow-up:',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'sales_item31',
                'html' => 'Assign next follow-up task date in CRM',
                'value' => 'checked',
            ],
        ],
        'form' => [
            'id' => 'checklist-form-sales-4',
            'action' => 'https://n8n.srv1338052.hstgr.cloud/webhook/sales-checklists',
            'target' => 'hidden_iframe_sales_4',
            'checklist_name' => 'Missed Meeting - Follow-Up Checklist',
            'require_name' => false,
        ],
    ],
    [
        'kind' => 'checklist',
        'title' => 'Prospect Canceled Meeting (Without Rescheduling)',
        'blocks' => [
            [
                'kind' => 'text',
                'name' => 'salespersonName',
                'label' => 'Salesperson Name:',
            ],
            [
                'kind' => 'item',
                'depth' => 0,
                'name' => 'canceled_item1',
                'html' => 'Call prospect to reschedule',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'canceled_item1a',
                'html' => 'No pickup? <strong>Dial again, then leave voicemail</strong>',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'canceled_item1b',
                'html' => 'Email “<strong>0. Hiring Virtual Assistant CANCELED Meeting</strong>”',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'canceled_item1c',
                'html' => 'Text letting them know you saw they <em>canceled the upcoming call</em> and ask if they still want to look into hiring a Virtual Assistant',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 0,
                'name' => 'canceled_item2',
                'html' => 'Change Deal status to Missed Meeting',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 0,
                'name' => 'canceled_item3',
                'html' => 'Change Field Next Step? To “Missed Meeting”',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 0,
                'name' => 'canceled_item4',
                'html' => 'Change Field Meeting Status to “Canceled”',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 0,
                'name' => 'canceled_item5',
                'html' => 'Set follow up next day: Call, Text, and Email all over again',
                'value' => 'checked',
            ],
        ],
        'form' => [
            'id' => 'checklist-form-canceled',
            'action' => 'https://n8n.srv1338052.hstgr.cloud/webhook/sales-checklists',
            'target' => 'hidden_iframe_canceled',
            'checklist_name' => 'Prospect Canceled Meeting (Without Rescheduling)',
            'require_name' => false,
        ],
    ],
    [
        'kind' => 'checklist',
        'title' => 'No Deal Found Checklist',
        'blocks' => [
            [
                'kind' => 'item',
                'depth' => 0,
                'name' => 'deal_item1_parent',
                'html' => 'Search thoroughly by:',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'deal_item1a',
                'html' => 'Name',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'deal_item1b',
                'html' => 'Phone',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'deal_item1c',
                'html' => 'Email',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 0,
                'name' => 'deal_item2',
                'html' => 'Click on “Global Add” (Plus button on top right of the CRM)',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 0,
                'name' => 'deal_item3_parent',
                'html' => 'Click Contact',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'deal_item3a',
                'html' => 'Add First Name, Last Name, Email, Phone',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'deal_item3b',
                'html' => 'Contact source: Manual Entry',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'deal_item3c',
                'html' => 'Click Submit',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 0,
                'name' => 'deal_item4',
                'html' => 'Find and open the contact you just created',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 0,
                'name' => 'deal_item5_parent',
                'html' => 'Click on Related Deals tab and click Add Deal',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'deal_item5a',
                'html' => 'Name: First Name Last Name – Hiring Virtual Assistant',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'deal_item5b',
                'html' => 'Stage: Appointment booked or whatever is appropriate stage',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'deal_item5c',
                'html' => 'Value: $3500',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'deal_item5d',
                'html' => 'Close Date: Today’s Date',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'deal_item5e',
                'html' => 'Owner: Assign to yourself',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'deal_item5f',
                'html' => 'Collaborator: Assign to yourself',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'deal_item5g',
                'html' => 'Deal Source: Manual Entry',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'deal_item5h',
                'html' => 'Consultation Date: Write down the consultation date',
                'value' => 'checked',
            ],
        ],
        'form' => [
            'id' => 'checklist-form-no-deal',
            'action' => 'https://n8n.srv1338052.hstgr.cloud/webhook/sales-checklists',
            'target' => 'hidden_iframe_no_deal',
            'checklist_name' => 'No Deal Found Checklist',
            'require_name' => false,
        ],
    ],
    [
        'kind' => 'checklist',
        'title' => 'Capture Payment Checklist',
        'blocks' => [
            [
                'kind' => 'text',
                'name' => 'salespersonName',
                'label' => 'Salesperson Name:',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Capture Payment',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'payment_item1',
                'html' => 'Go to Stripe.com and login using sales@remoteleverage.com | Login security code will be sent to OpenPhone login prompts number in your account',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'payment_item2',
                'html' => 'Click on Transactions on the left side',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'payment_item3',
                'html' => 'Find the payment that has “Uncaptured” and click on it to open it, then click on “Capture Payment” at the top right side to capture the payment',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'payment_item4',
                'html' => 'Confirm payment status says “Succeeded”',
                'value' => 'checked',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Schedule Hiring Manager Call Again',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'payment_item5',
                'html' => '<a href="https://calendly.com/d/cnk9-psf-7k8/remote-leverage-onboarding-applicant-criteria-paid" target="_blank" rel="noopener">Click here</a> to go to Calendly again to schedule the onboarding call without needing to collect payment again',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'payment_item6',
                'html' => 'Schedule the call and announce in the group chat to let the Hiring Manager know to handle the new client',
                'value' => 'checked',
            ],
        ],
        'form' => [
            'id' => 'checklist-form-payment-1',
            'action' => 'https://n8n.srv1338052.hstgr.cloud/webhook/sales-checklists',
            'target' => 'hidden_iframe_payment_1',
            'checklist_name' => 'Capture Payment Checklist',
            'require_name' => true,
        ],
    ],
    [
        'kind' => 'checklist',
        'title' => 'Manually Book HM Onboarding Call (When Clients Use Apple Pay/Direct Transfer/Etc)',
        'blocks' => [
            [
                'kind' => 'text',
                'name' => 'salespersonName',
                'label' => 'Salesperson Name:',
            ],
            [
                'kind' => 'group',
                'depth' => 0,
                'title' => 'Book HM Onboarding Call',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'hm_item1',
                'html' => '<a href="https://calendly.com/d/cnk9-psf-7k8/remote-leverage-onboarding-applicant-criteria-paid" target="_blank" rel="noopener">Click here</a> to go to Calendly and schedule the onboarding call (no payment needed)',
                'value' => 'checked',
            ],
            [
                'kind' => 'item',
                'depth' => 1,
                'name' => 'hm_item2',
                'html' => 'Schedule the call and announce in the group chat to let the Hiring Manager know to handle the new client',
                'value' => 'checked',
            ],
        ],
        'form' => [
            'id' => 'hmcall-1-form',
            'action' => 'https://n8n.srv1338052.hstgr.cloud/webhook/sales-checklists',
            'target' => 'hmcall-1-iframe',
            'checklist_name' => 'Manually Book HM Onboarding Call (When Clients Use Apple Pay/Direct Transfer/Etc)',
            'require_name' => false,
        ],
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
<!-- /wp:html -->
