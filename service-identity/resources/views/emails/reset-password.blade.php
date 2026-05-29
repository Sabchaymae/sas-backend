@extends('emails.layout')

@section('title', 'Réinitialisation de mot de passe — Oriotel')
@section('banner_title', 'Réinitialisation de mot de passe')

@section('content')
    <p style="margin: 0 0 24px; color: #111827; font-size: 16px; line-height: 1.6;">
        Bonjour <strong>{{ $user->first_name }} {{ $user->last_name }}</strong>,
    </p>

    <p style="margin: 0 0 24px; color: #374151; font-size: 15px; line-height: 1.7;">
        Nous avons reçu une demande de réinitialisation du mot de passe associé à votre compte Oriotel.
        Cliquez sur le bouton ci-dessous pour définir un nouveau mot de passe :
    </p>

    <!-- CTA Button -->
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 24px;">
        <tr>
            <td align="center">
                <a href="{{ $resetUrl }}"
                   style="display: inline-block; background-color: #1428C9; color: #ffffff; font-size: 16px; font-weight: 700; text-decoration: none; padding: 16px 48px; border-radius: 8px; letter-spacing: 0.5px;">
                    Réinitialiser mon mot de passe
                </a>
            </td>
        </tr>
    </table>

    <!-- Fallback URL -->
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 24px;">
        <tr>
            <td style="background-color: #F9FAFB; padding: 14px 18px; border-radius: 6px;">
                <p style="margin: 0 0 6px; color: #6B7280; font-size: 12px;">Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :</p>
                <p style="margin: 0; color: #374151; font-size: 12px; word-break: break-all;">
                    <a href="{{ $resetUrl }}" style="color: #1428C9; text-decoration: none;">{{ $resetUrl }}</a>
                </p>
            </td>
        </tr>
    </table>

    <!-- Expiration Warning -->
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 24px;">
        <tr>
            <td style="background-color: #FEF3C7; border-left: 4px solid #F59E0B; padding: 14px 18px; border-radius: 0 6px 6px 0;">
                <p style="margin: 0; color: #92400E; font-size: 13px; line-height: 1.5;">
                    ⏱ Ce lien est valide pendant <strong>60 minutes</strong>. Passé ce délai, vous devrez effectuer une nouvelle demande.
                </p>
            </td>
        </tr>
    </table>

    <!-- Security Notice -->
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td style="background-color: #EEF2FF; border-left: 4px solid #1428C9; padding: 14px 18px; border-radius: 0 6px 6px 0;">
                <p style="margin: 0; color: #312E81; font-size: 13px; line-height: 1.5;">
                    🔒 <strong>Sécurité :</strong> Si vous n'avez pas demandé cette réinitialisation, aucune action n'est requise. Votre mot de passe actuel reste inchangé.
                </p>
            </td>
        </tr>
    </table>
@endsection
