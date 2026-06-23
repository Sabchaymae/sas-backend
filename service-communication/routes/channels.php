<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
}, ['guards' => ['sanctum']]);

Broadcast::channel('chat.{id}', function ($user, $id) {
    if (\App\Models\ConversationParticipant::where('conversation_id', $id)->where('user_id', $user->id)->exists()) {
        return ['id' => $user->id, 'name' => $user->name];
    }
    return false;
}, ['guards' => ['sanctum']]);

Broadcast::channel('notifications.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
}, ['guards' => ['sanctum']]);

Broadcast::channel('presence.chat', function ($user) {
    return ['id' => $user->id, 'name' => $user->name];
}, ['guards' => ['sanctum']]);

Broadcast::channel('conversation.{id}', function ($user, $id) {
    if (\App\Models\ConversationParticipant::where('conversation_id', $id)->where('user_id', $user->id)->exists()) {
        return ['id' => $user->id, 'name' => $user->name];
    }
    return false;
}, ['guards' => ['sanctum']]);

// Public channel — any authenticated user can subscribe
Broadcast::channel('announcements', function ($user) {
    return ['id' => $user->id, 'name' => $user->name];
}, ['guards' => ['sanctum']]);
