<?php

namespace App\Services;

use App\Interfaces\Services\IMailjetService;
use Exception;
use Mailjet\Client;
use Mailjet\Resources;

class MailjetService implements IMailjetService
{
    private Client $mailJetClient;

    public function __construct()
    {
        $this->mailJetClient = new Client(env("MAILJET_SECRETKEY", "xpto"), env("MAILJET_PUBLICKEY", "xpto"), true, ['version' => 'v3.1']);
    }

    public function send($email, $name, $subject, $message){
        $response = $this->mailJetClient->post(Resources::$Email, ['body' => $this->mountEmailBody($email, $name, $subject, $message)]);

        if (!$response->success()){
            throw new Exception("It wasn't possible to send your email. Please try again later.");
        }
    }

    private function mountEmailBody($email, $name, $subject, $message)
    {
        return [
            'Messages' => [
                [
                    'From' => [
                        'Email' => env("MAILJET_SENDEREMAIL"),
                        'Name' => env("APP_NAME")
                    ],
                    'To' => [
                        [
                            'Email' => $email,
                            "Name" => $name
                        ]
                    ],
                    'Subject' => $subject,
                    'HTMLPart' => $message
                ]
            ]
        ];
    }
}
