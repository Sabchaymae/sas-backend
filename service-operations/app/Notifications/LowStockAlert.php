<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Product;

class LowStockAlert extends Notification
{
    use Queueable;

    protected $product;

    /**
     * Create a new notification instance.
     */
    public function __construct(Product $product)
    {
        $this->product = $product;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->subject('⚠️ Alerte Stock Critique : ' . $this->product->designation)
                    ->greeting('Bonjour Admin,')
                    ->line('Le produit suivant a atteint un niveau de stock critique :')
                    ->line('Produit : ' . $this->product->designation)
                    ->line('SKU : ' . $this->product->sku)
                    ->line('Stock Actuel : ' . $this->product->quantite)
                    ->line('Seuil d\'alerte : ' . $this->product->seuil)
                    ->action('Gérer le stock', url('/stock'))
                    ->line('Un brouillon de bon de commande a été généré automatiquement pour ' . $this->product->fournisseur . '.')
                    ->line('Merci d\'utiliser ORIOTEL.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'product_id' => $this->product->id,
            'title' => 'Alerte Stock Bas',
            'message' => 'Le produit ' . $this->product->designation . ' est à ' . $this->product->quantite . ' unités.',
            'type' => 'stock_alert',
            'fournisseur' => $this->product->fournisseur
        ];
    }
}
