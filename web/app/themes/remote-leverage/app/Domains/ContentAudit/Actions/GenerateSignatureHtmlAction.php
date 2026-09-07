<?php

declare(strict_types=1);

namespace App\Domains\ContentAudit\Actions;

class GenerateSignatureHtmlAction
{
    /**
     * Generate HTML email signature markup matching rl-social-kit templates (sig-1, sig-2, sig-3 in light/dark).
     */
    public function execute(array $data): string
    {
        $layout = (int) ($data['template'] ?? $data['layout'] ?? 1);
        if (! in_array($layout, [1, 2, 3], true)) {
            $layout = 1;
        }

        $theme = strtolower((string) ($data['theme'] ?? 'light'));
        if (! in_array($theme, ['light', 'dark'], true)) {
            $theme = 'light';
        }

        $tplKey = "sig-{$layout}-{$theme}";

        // Locate template file
        $tplPath = null;
        if (function_exists('resource_path')) {
            $candidate = resource_path("views/signatures/{$tplKey}.html");
            if (file_exists($candidate)) {
                $tplPath = $candidate;
            }
        }
        if (! $tplPath) {
            $candidate = dirname(__DIR__, 4)."/resources/views/signatures/{$tplKey}.html";
            if (file_exists($candidate)) {
                $tplPath = $candidate;
            }
        }

        $templateHtml = $tplPath && file_exists($tplPath) ? file_get_contents($tplPath) : '';

        if (empty($templateHtml)) {
            return '';
        }

        $name = htmlspecialchars($data['name'] ?? 'Adrian Salvatori', ENT_QUOTES, 'UTF-8');
        $title = htmlspecialchars($data['title'] ?? 'Managing Director', ENT_QUOTES, 'UTF-8');
        $email = htmlspecialchars($data['email'] ?? 'adrian@remoteleverage.com', ENT_QUOTES, 'UTF-8');
        $phone = ! empty($data['phone']) ? htmlspecialchars($data['phone'], ENT_QUOTES, 'UTF-8') : (! empty($data['office_phone']) ? htmlspecialchars($data['office_phone'], ENT_QUOTES, 'UTF-8') : '');
        $mobile = ! empty($data['mobile']) ? htmlspecialchars($data['mobile'], ENT_QUOTES, 'UTF-8') : '';
        $website = htmlspecialchars($data['website'] ?? 'https://remoteleverage.com', ENT_QUOTES, 'UTF-8');
        $websiteDisplay = htmlspecialchars($data['website_display'] ?? 'remoteleverage.com', ENT_QUOTES, 'UTF-8');
        $address1 = htmlspecialchars($data['address_line1'] ?? '1309 Coffeen Avenue STE 1200', ENT_QUOTES, 'UTF-8');
        $address2 = htmlspecialchars($data['address_line2'] ?? 'Sheridan, WY 82801', ENT_QUOTES, 'UTF-8');

        // Assets
        $baseUrl = $this->getBaseAssetUrl();
        $logoIconWhite = $data['logo_icon_white'] ?? ($baseUrl.'logo-icon-white.svg');
        $logoIconBlack = $data['logo_icon_black'] ?? ($baseUrl.'logo-icon-black.svg');

        $logoUrl = $data['logo_url'] ?? '';
        if (empty($logoUrl)) {
            $logoUrl = $theme === 'dark' ? ($baseUrl.'rl-logo-5.png') : ($baseUrl.'rl-logo-6.png');
        }

        $avatarUrl = ! empty($data['avatar_url']) ? $data['avatar_url'] : ($baseUrl.'avatar.png');

        // Formatting phone & mobile
        $themeTextMuted = $theme === 'dark' ? '#DCE3EB' : '#4A5568';
        $cleanPhone = preg_replace('/[^+\d]/', '', $phone);
        $cleanMobile = preg_replace('/[^+\d]/', '', $mobile);

        $phoneRow = $phone ? '<tr><td style="padding-bottom: 2px; color: '.$themeTextMuted.'; font-family: Helvetica, Arial, sans-serif;">P: <a href="tel:'.$cleanPhone.'" style="color: '.$themeTextMuted.'; text-decoration: none;">'.$phone.'</a></td></tr>' : '';
        $mobileRow = $mobile ? '<tr><td style="padding-bottom: 2px; color: '.$themeTextMuted.'; font-family: Helvetica, Arial, sans-serif;">M: <a href="tel:'.$cleanMobile.'" style="color: '.$themeTextMuted.'; text-decoration: none;">'.$mobile.'</a></td></tr>' : '';

        // Social cells
        $social = $data['social'] ?? [];
        $socialLinks = [
            'linkedin' => $social['linkedin'] ?? ($data['linkedin'] ?? 'https://www.linkedin.com/company/remoteleverage'),
            'twitter' => $social['twitter'] ?? ($data['twitter'] ?? 'https://x.com/remoteleverage'),
            'facebook' => $social['facebook'] ?? ($data['facebook'] ?? ''),
            'instagram' => $social['instagram'] ?? ($data['instagram'] ?? ''),
            'youtube' => $social['youtube'] ?? ($data['youtube'] ?? ''),
        ];

        $socialIcons = [
            'linkedin' => 'ln.png',
            'facebook' => 'fb.png',
            'instagram' => 'ig.png',
            'twitter' => 'x.png',
            'youtube' => 'yt.png',
        ];

        $socialCells = '';
        foreach ($socialIcons as $key => $iconFile) {
            if (! empty($socialLinks[$key])) {
                $iconUrl = $baseUrl.$iconFile;
                $escUrl = htmlspecialchars($socialLinks[$key], ENT_QUOTES, 'UTF-8');
                $escKey = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
                $socialCells .= <<<HTML
<td style="padding: 0 6px 0 0; vertical-align: middle;">
    <a href="{$escUrl}" target="_blank" style="text-decoration: none; display: block;">
        <img src="{$iconUrl}" width="20" height="20" alt="{$escKey}" style="border: 0; display: block;" />
    </a>
</td>
HTML;
            }
        }

        $replacements = [
            '{{NAME}}' => $name,
            '{{TITLE}}' => $title,
            '{{EMAIL}}' => $email,
            '{{AVATAR_URL}}' => htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8'),
            '{{LOGO_URL}}' => htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8'),
            '{{LOGO_ICON_WHITE}}' => htmlspecialchars($logoIconWhite, ENT_QUOTES, 'UTF-8'),
            '{{LOGO_ICON_BLACK}}' => htmlspecialchars($logoIconBlack, ENT_QUOTES, 'UTF-8'),
            '{{ADDRESS_LINE1}}' => $address1,
            '{{ADDRESS_LINE2}}' => $address2,
            '{{WEBSITE}}' => $website,
            '{{WEBSITE_DISPLAY}}' => $websiteDisplay,
            '{{SOCIAL_CELLS}}' => $socialCells,
            '{{PHONE_ROW}}' => $phoneRow,
            '{{MOBILE_ROW}}' => $mobileRow,
        ];

        return strtr($templateHtml, $replacements);
    }

    protected function getBaseAssetUrl(): string
    {
        if (function_exists('home_url')) {
            $base = home_url();
        } else {
            $base = 'https://remoteleverage.com';
        }

        if (function_exists('get_stylesheet_directory_uri')) {
            $themeUri = get_stylesheet_directory_uri();
            if ($themeUri) {
                return rtrim($themeUri, '/').'/resources/images/';
            }
        }

        return rtrim($base, '/').'/app/themes/remote-leverage/resources/images/';
    }
}
