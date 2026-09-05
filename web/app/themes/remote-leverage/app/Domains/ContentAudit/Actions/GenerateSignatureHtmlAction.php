<?php

declare(strict_types=1);

namespace App\Domains\ContentAudit\Actions;

class GenerateSignatureHtmlAction
{
    /**
     * Generate HTML email signature markup matching rl-social-kit templates.
     */
    public function execute(array $data): string
    {
        $name = htmlspecialchars($data['name'] ?? 'Team Member', ENT_QUOTES, 'UTF-8');
        $title = htmlspecialchars($data['title'] ?? 'Remote Leverage Specialist', ENT_QUOTES, 'UTF-8');
        $email = htmlspecialchars($data['email'] ?? 'hello@remoteleverage.com', ENT_QUOTES, 'UTF-8');
        $phone = htmlspecialchars($data['phone'] ?? '+1 (800) 000-0000', ENT_QUOTES, 'UTF-8');
        $mobile = ! empty($data['mobile']) ? htmlspecialchars($data['mobile'], ENT_QUOTES, 'UTF-8') : null;
        $website = htmlspecialchars($data['website'] ?? 'https://remoteleverage.com', ENT_QUOTES, 'UTF-8');
        $websiteDisplay = htmlspecialchars($data['website_display'] ?? 'remoteleverage.com', ENT_QUOTES, 'UTF-8');
        $address1 = htmlspecialchars($data['address_line1'] ?? '1309 Coffeen Avenue STE 1200', ENT_QUOTES, 'UTF-8');
        $address2 = htmlspecialchars($data['address_line2'] ?? 'Sheridan, WY 82801', ENT_QUOTES, 'UTF-8');
        $logoIcon = $data['logo_icon_url'] ?? 'https://remoteleverage.com/wp-content/uploads/logo-icon-white.svg';
        $logoUrl = $data['logo_url'] ?? 'https://remoteleverage.com/wp-content/uploads/rl-logo.png';

        $phoneRow = $phone ? '<tr><td style="padding-bottom: 2px; color: #4A5568; font-family: Helvetica, Arial, sans-serif;">P: <a href="tel:'.$phone.'" style="color: #4A5568; text-decoration: none;">'.$phone.'</a></td></tr>' : '';
        $mobileRow = $mobile ? '<tr><td style="padding-bottom: 2px; color: #4A5568; font-family: Helvetica, Arial, sans-serif;">M: <a href="tel:'.$mobile.'" style="color: #4A5568; text-decoration: none;">'.$mobile.'</a></td></tr>' : '';

        return <<<HTML
<table cellpadding="0" cellspacing="0" border="0" style="background-color: #F4F6FC; width: 600px; height: 200px; min-width: 600px; max-width: 600px; font-family: Helvetica, Arial, sans-serif; text-align: left; border-collapse: separate; mso-table-lspace: 0pt; mso-table-rspace: 0pt;">
    <tr>
        <!-- Left Dome Section -->
        <td valign="middle" align="center" bgcolor="#8A2BE2" width="163" style="width: 163px; background-color: #8A2BE2; border-top-right-radius: 100px; border-bottom-right-radius: 100px; text-align: center; vertical-align: middle; padding: 0;">
            <img src="{$logoIcon}" width="110" height="110" alt="Brand Icon" style="display: block; margin: 0 auto; border: 0;" />
        </td>
        
        <!-- Right Info Section -->
        <td valign="top" style="padding: 24px 28px 24px 28px; vertical-align: top;">
            <table cellpadding="0" cellspacing="0" border="0" style="width: 100%; height: 152px; border-collapse: collapse;">
                <tr>
                    <!-- Left-middle Column: Identity & Socials -->
                    <td valign="top" style="width: 210px; vertical-align: top; padding: 0;">
                        <table cellpadding="0" cellspacing="0" border="0" style="width: 100%; height: 152px; border-collapse: collapse;">
                            <tr>
                                <td valign="top" style="vertical-align: top; padding: 0;">
                                    <div style="font-size: 20px; font-weight: bold; color: #1A202C; line-height: 1.2; font-family: Helvetica, Arial, sans-serif; letter-spacing: -0.5px;">{$name}</div>
                                    <div style="font-size: 14px; color: #4A5568; font-weight: normal; margin-top: 4px; font-family: Helvetica, Arial, sans-serif;">{$title}</div>
                                </td>
                            </tr>
                        </table>
                    </td>
                    
                    <!-- Middle Spacer -->
                    <td style="width: 20px; padding: 0;"></td>
                    
                    <!-- Right-middle Column: Contact Info & Brand Logo -->
                    <td valign="top" style="vertical-align: top; padding: 0;">
                        <table cellpadding="0" cellspacing="0" border="0" style="width: 100%; height: 152px; border-collapse: collapse;">
                            <tr>
                                <td valign="top" style="vertical-align: top; padding: 0; font-size: 10px; line-height: 1.35; color: #4A5568; font-family: Helvetica, Arial, sans-serif;">
                                    <table cellpadding="0" cellspacing="0" border="0" style="border-collapse: collapse; font-size: 10px; line-height: 1.35; color: #4A5568;">
                                        <tr>
                                            <td style="font-weight: bold; color: #1A202C; padding-bottom: 2px; font-family: Helvetica, Arial, sans-serif;">Address</td>
                                        </tr>
                                        <tr>
                                            <td style="padding-bottom: 2px; color: #4A5568; font-family: Helvetica, Arial, sans-serif; white-space: nowrap;">{$address1}</td>
                                        </tr>
                                        <tr>
                                            <td style="padding-bottom: 2px; color: #4A5568; font-family: Helvetica, Arial, sans-serif; white-space: nowrap;">{$address2}</td>
                                        </tr>
                                        {$phoneRow}
                                        {$mobileRow}
                                        <tr>
                                            <td style="padding-bottom: 2px; font-family: Helvetica, Arial, sans-serif;"><a href="mailto:{$email}" style="color: #4A5568; text-decoration: none;">{$email}</a></td>
                                        </tr>
                                        <tr>
                                            <td style="padding-bottom: 0; font-family: Helvetica, Arial, sans-serif;"><a href="{$website}" target="_blank" style="color: #1A202C; text-decoration: none; font-weight: bold;">{$websiteDisplay}</a></td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            <tr>
                                <td valign="bottom" style="vertical-align: bottom; padding: 0; height: 35px;">
                                    <img src="{$logoUrl}" height="28" alt="Remote Leverage" style="display: block; border: 0; margin: 0; width: auto;" />
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
HTML;
    }
}
