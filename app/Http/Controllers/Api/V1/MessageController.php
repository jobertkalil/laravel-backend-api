<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Message;
use App\Http\Requests\UpdateMessageRequest;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\MessageResource;
use App\Http\Resources\V1\MessageCollection;
use App\Http\Requests\V1\StoreMessageRequest;

use Storage;

class MessageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return new MessageCollection(Message::paginate());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMessageRequest $request)
    {
        $data = $request->all();
        $data['sender']="user";
        Message::create($data);

        //assess messsage
        $response = $this->assessMessage($data['message']);

        return new MessageResource(Message::create([
            "messageID" => $data['conversation_id'],
            "text" => $response,
            "sender" => "bot"
        ]));
    }

    /**
     * Display the specified resource.
     */
    public function show(Message $message)
    {
        return new MessageResource($message);
    }

    private function assessMessage(string $context) {
        $matrix = json_decode(Storage::disk("local")->get('api\v1\context.json'));
        $response = "";

        foreach($matrix->context as $rule) {
            $match = false;
            foreach($rule->keywords as $keyword) {
                $pattern = "/\b" . preg_quote($keyword, '/') . "\b/i";

                if (preg_match($pattern, $context)) {
                    $match = true;
                    break;
                }
            }

            $response = $this->assessResponse($match, $response, $rule);
        }

        if(strlen($response) <= 0) {
            return $matrix->default->response;
        } else {
            return $response;
        }
    }

    private function assessResponse(bool $match, string $response, $rule): string {
        if ($match === true) {
            if (strlen($response) > 0) {
                $response .= " " . $rule->response;
            } else {
                $response = $rule->response;
            }
        }
        return $response;
    }
}
