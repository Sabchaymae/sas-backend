@extends('emails.layout')

@section('title', 'Code de vérification — Oriotel')
@section('banner_title', 'Code de vérification')

@section('content')
    <p style="margin: 0 0 24px; color: #111827; font-size: 16px; line-height: 1.6;">
        Bonjour <strong>{{ $first_name }} {{ $last_name }}</strong>,
    </p>

    <p style="margin: 0 0 24px; color: #374151; font-size: 15px; line-height: 1.7;">
        Nous avons reçu une demande de vérification pour votre compte Oriotel.
        Veuillez utiliser le code ci-dessous pour compléter la vérification :
    </p>

    <!-- Code Block -->
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 24px;">
        <tr>
            <td align="center">
                <div style="display: inline-block; background-color: #111827; padding: 20px 48px; border-radius: 10px; border: 2px solid #1428C9;">
                    <span style="font-size: 36px; font-weight: 800; color: #1428C9; letter-spacing: 12px; font-family: 'Courier New', monospace;">{{ $code }}</span>
                </div>
            </td>
        </tr>
    </table>

    <!-- Expiration Warning -->
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 24px;">
        <tr>
            <td style="background-color: #FEF3C7; border-left: 4px solid #F59E0B; padding: 14px 18px; border-radius: 0 6px 6px 0;">
                <p style="margin: 0; color: #92400E; font-size: 13px; line-height: 1.5;">
                    ⏱ Ce code est valide pendant <strong>10 minutes</strong>. Passé ce délai, vous devrez en demander un nouveau.
                </p>
            </td>
        </tr>
    </table>

    <!-- Security Notice -->
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td style="background-color: #EEF2FF; border-left: 4px solid #1428C9; padding: 14px 18px; border-radius: 0 6px 6px 0;">
                <p style="margin: 0; color: #312E81; font-size: 13px; line-height: 1.5;">
                    🔒 <strong>Sécurité :</strong> Ne partagez jamais ce code avec qui que ce soit. L'équipe Oriotel ne vous demandera jamais votre code de vérification.
                </p>
            </td>
        </tr>
    </table>
@endsection
