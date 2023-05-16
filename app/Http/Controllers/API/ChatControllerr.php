<?php

namespace App\Http\Controllers\API;

use App\Events\SendMessage;
use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\User;
use App\Notifications\ChatMessageNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ChatControllerr extends Controller
{
    public function sendMessage(Request $request)
    {
        $request->validate([
            'to_id' => 'required',
            'message' => 'required|string|max:255',
        ]);
        $message = $request->input('message');
        $fromId = Auth::id();
        $toId = $request->input('to_id');
        $chat = DB::table('messages')
            ->where(function ($query) use ($fromId, $toId) {
                $query->where('from_id', $fromId)
                    ->where('to_id', $toId);
            })
            ->orWhere(function ($query) use ($fromId, $toId) {
                $query->where('from_id', $toId)
                    ->where('to_id', $fromId);
            })
            ->select('chat_id')
            ->first();
        if ($chat) {
            // Use existing chat
            $chatId = $chat->chat_id;
        } else {
            // Create new chat
            $chatId = DB::table('chats')->insertGetId([]);
            DB::table('messages')->insert([
                'chat_id' => $chatId,
                'from_id' => $fromId,
                'to_id' => $toId,
                'message' => '',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $toUser = User::find($toId);
        $notification = new ChatMessageNotification($message);
        $toUser->notify($notification);


        // Update the message in the messages table
        $messageModel = new Message([
            'chat_id' => $chatId,
            'from_id' => $fromId,
            'to_id' => $toId,
            'message' => $message,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $event = new SendMessage($messageModel);
        broadcast($event);
        return response()->json(['status' => 'Message sent!']);
    }
}
