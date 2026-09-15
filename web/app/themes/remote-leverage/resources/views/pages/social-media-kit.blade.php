{{--
  Social Media Kit — port of the rl-social-kit plugin's [rl_social_kit] shortcode.

  The markup below is the plugin's `render_shortcode()` output element for element: the same
  ids, classes, data-attributes and ordering, because `resources/css/social-kit.css` and
  `resources/js/social-kit.js` are the plugin's files carried over verbatim and are written
  against exactly these hooks.

  Three deliberate departures, all noted inline where they happen:
    1. The kit is public (`rl_social_kit_require_login` defaults to '0' here, not '1'), so the
       login card only appears if someone switches the option back on.
    2. The empty state no longer prints a server filesystem path.
    3. Login-only affordances (avatar upload, log out) are hidden or disabled for anonymous
       visitors rather than rendered into a dead end.
--}}
@extends('layouts.app')

@php
    use App\Support\SocialKit;

    $currentUser = wp_get_current_user();
    $loggedIn = is_user_logged_in();
    $requireLogin = SocialKit::requireLogin();

    $linkedinResources = SocialKit::resources('linkedin');
    $instagramResources = SocialKit::resources('instagram');
    $facebookResources = SocialKit::resources('facebook');
    $otherResources = SocialKit::resources('other');
@endphp

@section('content')
  {{-- Full-bleed rather than the theme's 1380px container: the dashboard is a two-pane
       tool (250px sidebar + a grid whose signature cards prefer 620px), and squeezing it
       into the editorial container is what pushed the preview column off-screen. It keeps
       a generous max so it never sprawls on an ultra-wide display. --}}
  <div class="w-full max-w-[1800px] mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-10">
    @if ($requireLogin && ! $loggedIn)
      {{-- Login gate. Only reachable when rl_social_kit_require_login is explicitly '1'. --}}
      <div class="rl-social-kit-login-wrapper">
        <div class="rl-social-kit-login-card">
          <div class="rl-login-logo">
            <img src="{{ esc_url(SocialKit::companyLogo()) }}" alt="Company Logo" />
          </div>
          <h2>Member Login</h2>
          <p>Enter your website credentials to access the Social Media Kit.</p>

          <form id="rl-social-kit-login-form">
            <div class="form-group">
              <label for="rl-username">Username or Email</label>
              <input type="text" id="rl-username" name="username" required placeholder="Enter username..." autocomplete="username" />
            </div>
            <div class="form-group">
              <label for="rl-password">Password</label>
              <input type="password" id="rl-password" name="password" required placeholder="Enter password..." autocomplete="current-password" />
            </div>
            <div id="rl-login-error" class="error-msg"></div>
            <button type="submit" class="rl-btn-submit">
              <span class="btn-text">Authenticate</span>
              <div class="loader"></div>
            </button>
          </form>
        </div>
      </div>
    @else
      <div class="rl-social-kit-dashboard">
        <div class="rl-dash-container">
          <!-- Sidebar Navigation -->
          <aside class="rl-dash-sidebar">
            <ul class="rl-sidebar-menu">
              <li class="rl-menu-item active" data-tab="email-sig">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                <span>Email Signatures</span>
              </li>
              @if (! empty($linkedinResources))
                <li class="rl-menu-item" data-tab="linkedin">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"></path><rect x="2" y="9" width="4" height="12"></rect><circle cx="4" cy="4" r="2"></circle></svg>
                  <span>LinkedIn Covers</span>
                </li>
              @endif
              @if (! empty($instagramResources))
                <li class="rl-menu-item" data-tab="instagram">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg>
                  <span>Instagram Templates</span>
                </li>
              @endif
              @if (! empty($facebookResources))
                <li class="rl-menu-item" data-tab="facebook">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path></svg>
                  <span>Facebook Assets</span>
                </li>
              @endif
              @if (! empty($otherResources))
                <li class="rl-menu-item" data-tab="other">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                  <span>Other Resources</span>
                </li>
              @endif

              {{-- The plugin rendered this unconditionally. On a public page a log-out link is
                   meaningless to an anonymous visitor, so it only shows for a real session. --}}
              @if ($loggedIn)
                <li class="rl-menu-item rl-logout-item" style="margin-top: 20px; border-top: 1px solid var(--border-light); padding: 0;">
                  <a href="{{ esc_url(wp_logout_url(get_permalink())) }}" style="text-decoration: none; color: inherit; display: flex; align-items: center; gap: 12px; width: 100%; padding: 12px 18px;">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                    <span>Log Out</span>
                  </a>
                </li>
              @endif
            </ul>
          </aside>

          <!-- Main Viewport -->
          <main class="rl-dash-content">

            <!-- TAB 1: Signature Generator -->
            <section class="rl-tab-view active" id="view-email-sig">
              <div class="rl-grid-container">
                <!-- Configuration Panel -->
                <div class="rl-panel-config">
                  <h3 class="panel-title">Signature Details</h3>
                  <form id="rl-sig-form">
                    <div class="form-row">
                      <div class="form-group col">
                        <label for="sig-first-name">First Name</label>
                        <input type="text" id="sig-first-name" value="{{ esc_attr($currentUser->first_name) }}" required />
                      </div>
                      <div class="form-group col">
                        <label for="sig-last-name">Last Name</label>
                        <input type="text" id="sig-last-name" value="{{ esc_attr($currentUser->last_name) }}" required />
                      </div>
                    </div>
                    <div class="form-row">
                      <div class="form-group col">
                        <label for="sig-title">Job Title</label>
                        <input type="text" id="sig-title" placeholder="e.g., Senior Developer" required />
                      </div>
                      <div class="form-group col">
                        <label for="sig-dept">Department</label>
                        <input type="text" id="sig-dept" placeholder="e.g., Marketing" />
                      </div>
                    </div>
                    <div class="form-row">
                      <div class="form-group col">
                        <label for="sig-email">Email Address</label>
                        <input type="email" id="sig-email" value="{{ esc_attr($currentUser->user_email) }}" required />
                      </div>
                      <div class="form-group col">
                        <label for="sig-office-phone">Office Phone</label>
                        <input type="tel" id="sig-office-phone" placeholder="e.g., +1 (555) 019-2834" />
                      </div>
                    </div>
                    <div class="form-row">
                      <div class="form-group col">
                        <label>Profile Picture</label>
                        {{-- The avatar endpoint stays logged-in only (a nopriv handler would let
                             anyone write into the media library). So for anonymous visitors the
                             control is disabled rather than left to fail with "Unauthorized user."
                             Template 2 falls back to the brand mark when no avatar is set. --}}
                        <div class="rl-avatar-uploader-container">
                          <input type="hidden" id="sig-avatar-url" value="" />
                          <input type="file" id="rl-avatar-file-input" accept="image/*" style="display: none;" />
                          <button type="button" id="rl-avatar-select-btn" class="rl-btn-secondary" @if (! $loggedIn) disabled @endif>Upload Photo</button>
                          <span id="rl-avatar-status" class="status-indicator">{{ $loggedIn ? 'No photo selected' : 'Sign in to upload a photo' }}</span>
                        </div>
                      </div>
                    </div>

                    <h4 class="panel-subtitle">Personal Social Overrides (Optional)</h4>
                    <p class="panel-subtext">Leave empty to use global company social accounts.</p>

                    <div class="form-row">
                      <div class="form-group col">
                        <label for="sig-linkedin">LinkedIn Profile URL</label>
                        <input type="url" id="sig-linkedin" placeholder="https://linkedin.com/in/username" />
                      </div>
                      <div class="form-group col">
                        <label for="sig-twitter">Twitter / X URL</label>
                        <input type="url" id="sig-twitter" placeholder="https://x.com/username" />
                      </div>
                    </div>
                    <div class="form-row">
                      <div class="form-group col">
                        <label for="sig-facebook">Facebook URL</label>
                        <input type="url" id="sig-facebook" placeholder="https://facebook.com/username" />
                      </div>
                      <div class="form-group col">
                        <label for="sig-instagram">Instagram URL</label>
                        <input type="url" id="sig-instagram" placeholder="https://instagram.com/username" />
                      </div>
                    </div>

                  </form>
                </div>

                <!-- Preview & Export Panel -->
                <div class="rl-panel-preview">
                  <h3 class="panel-title">All Signature Styles</h3>

                  <div class="rl-signature-grid">
                    @foreach ([
                        ['template' => '1', 'theme' => 'light', 'title' => 'Brand Icon Dome', 'badge' => 'Light'],
                        ['template' => '1', 'theme' => 'dark', 'title' => 'Brand Icon Dome', 'badge' => 'Dark'],
                        ['template' => '2', 'theme' => 'light', 'title' => 'Profile Photo Dome', 'badge' => 'Light'],
                        ['template' => '2', 'theme' => 'dark', 'title' => 'Profile Photo Dome', 'badge' => 'Dark'],
                        ['template' => '3', 'theme' => 'light', 'title' => 'Standalone Icon', 'badge' => 'Light'],
                        ['template' => '3', 'theme' => 'dark', 'title' => 'Standalone Icon', 'badge' => 'Dark'],
                    ] as $card)
                      <div class="rl-sig-card" data-template="{{ $card['template'] }}" data-theme="{{ $card['theme'] }}">
                        <div class="rl-sig-card-header">
                          <span class="rl-sig-card-title">{{ $card['title'] }}</span>
                          <span class="rl-sig-card-badge {{ $card['theme'] }}">{{ $card['badge'] }}</span>
                        </div>
                        <div class="rl-sig-card-body">
                          <iframe class="rl-sig-iframe" id="iframe-sig-{{ $card['template'] }}-{{ $card['theme'] }}" title="{{ $card['title'] }} ({{ $card['badge'] }})"></iframe>
                        </div>
                        <div class="rl-sig-card-footer">
                          <button type="button" class="rl-btn-sig-action rl-copy-rich" data-template="{{ $card['template'] }}" data-theme="{{ $card['theme'] }}" title="Copy for Gmail/Outlook settings">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                            Copy
                          </button>
                          <button type="button" class="rl-btn-sig-action rl-copy-html" data-template="{{ $card['template'] }}" data-theme="{{ $card['theme'] }}" title="Copy HTML source code">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg>
                            HTML
                          </button>
                          <button type="button" class="rl-btn-sig-action rl-download" data-template="{{ $card['template'] }}" data-theme="{{ $card['theme'] }}" title="Download HTML file">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                            Download
                          </button>
                          <button type="button" class="rl-btn-sig-action rl-btn-setup" title="How to setup signature">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                            Setup
                          </button>
                        </div>
                      </div>
                    @endforeach
                  </div>

                  <div id="rl-copy-toast" class="toast-notification">Successfully copied to clipboard!</div>

                  <!-- Setup Instructions Modal -->
                  <div id="rl-setup-modal" class="rl-modal">
                    <div class="rl-modal-overlay"></div>
                    <div class="rl-modal-card">
                      <div class="rl-modal-header">
                        <h3>How to Set Up Your Email Signature</h3>
                        <button type="button" class="rl-modal-close">&times;</button>
                      </div>
                      <div class="rl-modal-body">
                        <div class="rl-modal-tabs">
                          <button type="button" class="rl-modal-tab-btn active" data-modal-tab="gmail">Gmail</button>
                          <button type="button" class="rl-modal-tab-btn" data-modal-tab="outlook">Outlook</button>
                          <button type="button" class="rl-modal-tab-btn" data-modal-tab="apple-mail">Apple Mail</button>
                        </div>

                        <div class="rl-modal-content-wrapper">
                          <!-- Gmail Content -->
                          <div class="rl-modal-tab-content active" id="modal-tab-gmail">
                            <ol class="setup-steps">
                              <li>Click the <strong style="color: var(--primary-color);">Copy</strong> button on your preferred signature card.</li>
                              <li>Open your <strong>Gmail</strong> in a web browser.</li>
                              <li>Click the <strong>Gear Icon</strong> in the top right and select <strong>"See all settings"</strong>.</li>
                              <li>Scroll down to the <strong>Signature</strong> section and click <strong>"Create new"</strong>.</li>
                              <li>Click inside the text editor box and paste the signature by pressing <code>Ctrl + V</code> (Windows) or <code>Cmd + V</code> (Mac).</li>
                              <li>Scroll to the bottom of the page and click <strong>"Save Changes"</strong>.</li>
                            </ol>
                          </div>

                          <!-- Outlook Content -->
                          <div class="rl-modal-tab-content" id="modal-tab-outlook">
                            <ol class="setup-steps">
                              <li>Click the <strong style="color: var(--primary-color);">Copy</strong> button on your preferred signature card.</li>
                              <li>Open <strong>Outlook</strong> (Web or Desktop app).</li>
                              <li>Go to <strong>Settings</strong> (Gear Icon) &rarr; <strong>"View all Outlook settings"</strong> &rarr; <strong>"Compose and reply"</strong>.</li>
                              <li>Under <strong>"Email signatures"</strong>, click <strong>"+ New signature"</strong>.</li>
                              <li>Paste your copied signature inside the text box using <code>Ctrl + V</code> (Windows) or <code>Cmd + V</code> (Mac).</li>
                              <li>Assign the signature to your default messages and click <strong>"Save"</strong>.</li>
                            </ol>
                          </div>

                          <!-- Apple Mail Content -->
                          <div class="rl-modal-tab-content" id="modal-tab-apple-mail">
                            <ol class="setup-steps">
                              <li>Click the <strong style="color: var(--primary-color);">Download</strong> button on your preferred signature card to save the HTML file.</li>
                              <li>Open <strong>Apple Mail</strong>, go to <strong>Mail</strong> &rarr; <strong>Settings...</strong> &rarr; <strong>Signatures</strong>.</li>
                              <li>Create a temporary placeholder signature under your desired account, then close settings and quit Mail.</li>
                              <li>In Finder, press <code>Cmd + Shift + G</code> and go to: <code style="font-size: 11px;">~/Library/Mail/V10/MailData/Signatures/</code> (V number may vary).</li>
                              <li>Sort by Date Modified, locate the newest <code>.mailsignature</code> file, and open it in a text editor.</li>
                              <li>Replace everything below the metadata headers with the contents of your downloaded HTML signature file, then save and lock the file (Get Info &rarr; Locked).</li>
                            </ol>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </section>

            <!-- TAB 2: LinkedIn Covers -->
            <section class="rl-tab-view" id="view-linkedin">
              <h3 class="tab-header-title">LinkedIn Covers & Assets</h3>
              <p class="tab-header-desc">Download branded LinkedIn header banners and covers to use on your professional profile.</p>
              @include('partials.social-kit-resource-grid', ['resources' => $linkedinResources])
            </section>

            {{-- TAB 3: Instagram Templates. The plugin has no sidebar entry for this pane (the
                 menu item is gated on the folder being non-empty, and it never is), so it is
                 unreachable markup upstream too. Kept for structural fidelity. --}}
            <section class="rl-tab-view" id="view-instagram">
              <h3 class="tab-header-title">Instagram Post & Story Templates</h3>
              <p class="tab-header-desc">Serving layouts, backgrounds, and assets for brand posts and stories.</p>
              @include('partials.social-kit-resource-grid', ['resources' => $instagramResources])
            </section>

            <!-- TAB 4: Facebook Assets -->
            <section class="rl-tab-view" id="view-facebook">
              <h3 class="tab-header-title">Facebook Covers & Assets</h3>
              <p class="tab-header-desc">Banners, assets, and graphics configured for Facebook profiles and brand pages.</p>
              @include('partials.social-kit-resource-grid', ['resources' => $facebookResources])
            </section>

            <!-- TAB 5: Other Resources -->
            <section class="rl-tab-view" id="view-other">
              <h3 class="tab-header-title">General Brand Resources</h3>
              <p class="tab-header-desc">Miscellaneous assets, logos, files, guidelines, and document resources.</p>
              @include('partials.social-kit-resource-grid', ['resources' => $otherResources])
            </section>

          </main>
        </div>
      </div>
    @endif
  </div>
@endsection
