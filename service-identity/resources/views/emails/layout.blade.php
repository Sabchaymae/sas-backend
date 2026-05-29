<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Oriotel')</title>
</head>
<body style="margin: 0; padding: 0; background-color: #F9FAFB; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; -webkit-font-smoothing: antialiased;">

    <!-- Wrapper Table -->
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #F9FAFB; padding: 40px 0;">
        <tr>
            <td align="center">

                <!-- Main Container -->
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width: 600px; width: 100%; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);">

                    <!-- Header with Logo -->
                    <tr>
                        <td style="background-color: #111827; padding: 32px 40px; text-align: center;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center">
                                        <img src="{{ $message->embed(public_path('oriotel-logo.png')) }}"
                                             alt="Oriotel"
                                             width="200"
                                             style="display: block; margin: 0 auto 12px; max-width: 200px; height: auto;" />
                                        <div style="width: 60px; height: 3px; background-color: #1428C9; margin: 0 auto 8px;"></div>
                                        <p style="margin: 0; color: rgba(255, 255, 255, 0.6); font-size: 12px; letter-spacing: 2px; text-transform: uppercase;">
                                            Enterprise Resource Planning
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Subject Banner -->
                    <tr>
                        <td style="background-color: #1428C9; padding: 14px 40px; text-align: center;">
                            <p style="margin: 0; color: #ffffff; font-size: 14px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase;">
                                @yield('banner_title')
                            </p>
                        </td>
                    </tr>

                    <!-- Body Content -->
                    <tr>
                        <td style="padding: 40px;">
                            @yield('content')
                        </td>
                    </tr>

                    <!-- Divider -->
                    <tr>
                        <td style="padding: 0 40px;">
                            <div style="height: 1px; background-color: #E5E7EB;"></div>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding: 24px 40px 32px; text-align: center;">
                            <p style="margin: 0 0 8px; color: #6B7280; font-size: 12px; line-height: 1.6;">
                                Cet email a été envoyé automatiquement par la plateforme <strong style="color: #111827;">Oriotel ERP</strong>.
                            </p>
                            <p style="margin: 0 0 8px; color: #6B7280; font-size: 12px; line-height: 1.6;">
                                Si vous n'avez pas initié cette action, veuillez ignorer cet email ou contacter notre support.
                            </p>
                            <p style="margin: 16px 0 0; color: #9CA3AF; font-size: 11px;">
                                &copy; {{ date('Y') }} Oriotel — Tous droits réservés.
                            </p>
                        </td>
                    </tr>

                </table>

                <!-- Post-footer -->
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width: 600px; width: 100%; margin-top: 16px;">
                    <tr>
                        <td align="center" style="padding: 8px 0;">
                            <p style="margin: 0; color: #9CA3AF; font-size: 11px;">
                                Pour toute question, contactez-nous à
                                <a href="mailto:support@oriotel.com" style="color: #1428C9; text-decoration: none;">support@oriotel.com</a>
                            </p>
                        </td>
                    </tr>
                </table>

            </td>
        </tr>
    </table>

</body>
</html>
