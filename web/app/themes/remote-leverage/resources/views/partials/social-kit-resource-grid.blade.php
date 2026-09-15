{{--
  Social Media Kit resource grid — port of the plugin's private `render_resource_grid()`.

  Markup is element-for-element identical to the plugin, with one change: the empty state no
  longer prints `wp-content/plugins/rl-social-kit/assets/resources/<tab>/`. Leaking a server
  filesystem path to every visitor was never intentional, and on a public page it is worse.
  The `.rl-empty-state` / `.empty-icon` hooks the stylesheet targets are preserved.

  @var array<int, array{name: string, fullname: string, url: string, size: string, extension: string, is_image: bool}> $resources
--}}
@if (empty($resources))
  <div class="rl-empty-state">
    <div class="empty-icon">&#128194;</div>
    <h4>No resources available yet</h4>
    <p>New assets will appear here as soon as they are added to the kit.</p>
  </div>
@else
  <div class="rl-resource-grid">
    @foreach ($resources as $res)
      <div class="rl-resource-card">
        <div class="rl-card-thumbnail">
          @if ($res['is_image'])
            <img src="{{ esc_url($res['url']) }}" alt="{{ esc_attr($res['name']) }}" loading="lazy" />
          @else
            <div class="rl-file-icon">{{ strtoupper($res['extension']) }}</div>
          @endif
        </div>
        <div class="rl-card-info">
          <h4 class="rl-resource-name" title="{{ esc_attr($res['fullname']) }}">{{ $res['name'] }}</h4>
          <div class="rl-resource-meta">
            <span class="file-size">{{ $res['size'] }}</span>
            <span class="file-ext">{{ strtoupper($res['extension']) }}</span>
          </div>
          <a href="{{ esc_url($res['url']) }}" download="{{ esc_attr($res['fullname']) }}" class="rl-download-card-btn">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
            Download
          </a>
        </div>
      </div>
    @endforeach
  </div>
@endif
