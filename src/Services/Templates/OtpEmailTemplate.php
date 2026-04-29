<?php
namespace App\Services\Templates;

class OtpEmailTemplate {

    /**
     * Returns the full HTML body for the OTP email.
     *
     * Logo loading strategy (hybrid):
     *   - localhost  → embeds logo as base64 (no URL needed)
     *   - production → uses public URL from APP_URL in .env
     *
     * @param string $clientName — client's name from #user-name input
     * @param string $otpCode    — 6-digit OTP code
     * @return string            — full HTML email body
     */
    public static function render(string $clientName, string $otpCode): string {

        // ✅ HYBRID LOGO: base64 on localhost, URL on production
        $logoPath = 'https://beta.misturadeluz.com/beta/images/logo.png';

        if (file_exists($logoPath)) {
            // localhost or server with file access — embed as base64
            $logoSrc = 'https://beta.misturadeluz.com/beta/images/logo.png';
        }

        $year = date('Y');

        return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seu código de verificação — Mistura de Luz</title>
</head>
<body style="margin:0;padding:0;background-color:#0f0a1e;font-family:'Georgia',serif;">

    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#0f0a1e;padding:40px 20px;">
        <tr>
            <td align="center">
                <table width="580" cellpadding="0" cellspacing="0" style="max-width:580px;width:100%;background:linear-gradient(160deg,#1a0f35 0%,#12082a 60%,#0d0620 100%);border-radius:24px;overflow:hidden;border:1px solid rgba(139,92,246,0.3);box-shadow:0 25px 60px rgba(139,92,246,0.2);">

                    <!-- TOP BAR -->
                    <tr>
                        <td style="height:4px;background:linear-gradient(90deg,#7c3aed,#a855f7,#ec4899,#a855f7,#7c3aed);"></td>
                    </tr>

                    <!-- HEADER -->
                    <tr>
                        <td align="center" style="padding:48px 40px 32px;">
                            <img src="https://beta.misturadeluz.com/beta/images/logo.png"
                                 alt="Mistura de Luz"
                                 width="230"
                                 style="display:block;margin:0 auto 20px auto;">
                            <h1 style="margin:0;font-size:28px;font-weight:400;color:#e9d5ff;letter-spacing:2px;font-style:italic;">
                                
                            <p style="margin:4px 0 0;font-size:11px;color:#9333ea;letter-spacing:4px;text-transform:uppercase;font-family:Arial,sans-serif;">
                               
                            </p>
                        </td>
                    </tr>

                    <!-- DIVIDER -->
                    <tr>
                        <td style="padding:0 40px;">
                            <div style="height:1px;background:linear-gradient(90deg,transparent,rgba(168,85,247,0.5),transparent);"></div>
                        </td>
                    </tr>

                    <!-- GREETING -->
                    <tr>
                        <td style="padding:40px 48px 24px;">
                            <p style="margin:0 0 10px;font-size:11px;color:#a78bfa;letter-spacing:3px;text-transform:uppercase;font-family:Arial,sans-serif;">
                                Verificação de identidade
                            </p>
                            <h2 style="margin:0 0 16px;font-size:24px;font-weight:400;color:#f3e8ff;font-style:italic;">
                                Olá, {$clientName} 🙏
                            </h2>
                            <p style="margin:0;font-size:15px;line-height:1.8;color:#c4b5fd;font-family:Arial,sans-serif;">
                                Recebemos uma solicitação de acesso à sua inscrição.<br>
                                Use o código abaixo para confirmar sua identidade e prosseguir:
                            </p>
                        </td>
                    </tr>

                    <!-- OTP CODE -->
                    <tr>
                        <td align="center" style="padding:8px 48px 40px;">
                            <table cellpadding="0" cellspacing="0" style="background:linear-gradient(135deg,rgba(124,58,237,0.2),rgba(168,85,247,0.1));border:2px solid rgba(168,85,247,0.5);border-radius:20px;">
                                <tr>
                                    <td align="center" style="padding:36px 60px;">
                                        <p style="margin:0 0 16px;font-size:11px;color:#a78bfa;letter-spacing:4px;text-transform:uppercase;font-family:Arial,sans-serif;">
                                            seu código
                                        </p>
                                        <p style="margin:0;font-size:52px;font-weight:700;letter-spacing:16px;color:#f3e8ff;font-family:'Courier New',monospace;text-shadow:0 0 30px rgba(168,85,247,0.8);">
                                            {$otpCode}
                                        </p>
                                        <p style="margin:20px 0 0;font-size:12px;color:#7c3aed;font-family:Arial,sans-serif;letter-spacing:1px;">
                                            &#9203; Válido por <strong style="color:#a78bfa;">5 minutos</strong>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- SECURITY NOTE -->
                    <tr>
                        <td style="padding:0 48px 40px;">
                            <table cellpadding="0" cellspacing="0" width="100%" style="background:rgba(124,58,237,0.1);border-left:3px solid #7c3aed;border-radius:0 12px 12px 0;">
                                <tr>
                                    <td style="padding:16px 20px;">
                                        <p style="margin:0;font-size:13px;color:#c4b5fd;line-height:1.7;font-family:Arial,sans-serif;">
                                            &#128274; <strong style="color:#e9d5ff;">Não solicitou este código?</strong><br>
                                            Ignore este e-mail com segurança. Nenhuma ação será tomada sem a confirmação do código.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- DIVIDER -->
                    <tr>
                        <td style="padding:0 40px;">
                            <div style="height:1px;background:linear-gradient(90deg,transparent,rgba(168,85,247,0.3),transparent);"></div>
                        </td>
                    </tr>

                    <!-- FOOTER -->
                    <tr>
                        <td align="center" style="padding:32px 48px 48px;">
                            <p style="margin:0 0 6px;font-size:13px;color:#7c3aed;font-family:Arial,sans-serif;">
                                Com amor e luz &#10024;
                            </p>
                            <p style="margin:0 0 20px;font-size:15px;color:#c4b5fd;font-style:italic;">
                                Equipe Mistura de Luz
                            </p>
                            <p style="margin:0;font-size:11px;color:#4c1d95;font-family:Arial,sans-serif;letter-spacing:1px;">
                                &copy; {$year} Mistura de Luz &mdash; Cursos e Terapias da Alma<br>
                                Este é um e-mail automático, por favor não responda.
                            </p>
                        </td>
                    </tr>

                    <!-- BOTTOM BAR -->
                    <tr>
                        <td style="height:4px;background:linear-gradient(90deg,#7c3aed,#a855f7,#ec4899,#a855f7,#7c3aed);"></td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>

</body>
</html>
HTML;
    }
}