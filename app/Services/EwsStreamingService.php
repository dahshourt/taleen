<?php

namespace App\Services;

use Exception;
use jamesiarmes\PhpEws\ArrayType\NonEmptyArrayOfBaseFolderIdsType;
use jamesiarmes\PhpEws\ArrayType\NonEmptyArrayOfNotificationEventTypesType;
use jamesiarmes\PhpEws\Client;
use jamesiarmes\PhpEws\Enumeration\DistinguishedFolderIdNameType;
use jamesiarmes\PhpEws\Enumeration\NotificationEventTypeType;
use jamesiarmes\PhpEws\Request\SubscribeType;
use jamesiarmes\PhpEws\Type\DistinguishedFolderIdType;
use Log;

class EwsStreamingService
{
    protected $client;

    protected $mailReader;

    protected $subscriptionId;

    protected $watermark;

    protected $running = false;

    public function __construct()
    {
        $host = config('services.ews.host');
        $username = config('services.ews.username');
        $password = config('services.ews.password');

        $this->client = new Client($host, $username, $password, Client::VERSION_2016);
        // $this->client->setCurlOptions([CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false]);
        if (config('services.ews.ssl_verify') === false) {
            $this->client->setCurlOptions([CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false]);
        }
        $this->mailReader = new EwsMailReader();
    }

    public function startListening()
    {
        try {
            // Create subscription
            $this->createSubscription();

            if (! $this->subscriptionId) {
                Log::error('Failed to create EWS subscription');

                return false;
            }

            Log::info('EWS Streaming service started with subscription: ' . $this->subscriptionId);

            $this->running = true;

            // Start the streaming loop
            $this->streamEvents();

        } catch (Exception $e) {
            Log::error('Error starting EWS streaming service: ' . $e->getMessage());

            return false;
        }
    }

    public function stopListening()
    {
        $this->running = false;

        if ($this->subscriptionId) {
            try {
                $this->unsubscribe();
            } catch (Exception $e) {
                Log::error('Error unsubscribing from EWS: ' . $e->getMessage());
            }
        }
    }

    protected function createSubscription()
    {
        $request = new SubscribeType();

        // Create a pull subscription request.
        $pullSubscription = new \jamesiarmes\PhpEws\Type\PullSubscriptionRequestType();

        // Set the folder to monitor (Inbox).
        $folderId = new DistinguishedFolderIdType();
        $folderId->Id = DistinguishedFolderIdNameType::INBOX;
        $pullSubscription->FolderIds = new NonEmptyArrayOfBaseFolderIdsType();
        $pullSubscription->FolderIds->DistinguishedFolderId[] = $folderId;

        // Set the event types to subscribe to.
        $eventTypes = new NonEmptyArrayOfNotificationEventTypesType();
        $eventTypes->EventType[] = NotificationEventTypeType::NEW_MAIL_EVENT;
        $eventTypes->EventType[] = NotificationEventTypeType::CREATED_EVENT;
        $pullSubscription->EventTypes = $eventTypes;

        // Set a timeout for the subscription (in minutes).
        $pullSubscription->Timeout = 10;

        $request->PullSubscriptionRequest = $pullSubscription;

        try {
            $response = $this->client->Subscribe($request);
        } catch (Exception $e) {
            // Log the full exception details for debugging.
            Log::error('Exception while creating EWS subscription', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }

        // Check the response for errors.
        $responseMessage = $response->ResponseMessages->SubscribeResponseMessage[0];
        if ($responseMessage->ResponseClass !== 'Success') {
            Log::error('Failed to create EWS subscription', [
                'ResponseClass' => $responseMessage->ResponseClass,
                'MessageText' => $responseMessage->MessageText,
                'ResponseCode' => $responseMessage->ResponseCode,
            ]);

            return false;
        }

        $this->subscriptionId = $responseMessage->SubscriptionId;
        $this->watermark = $responseMessage->Watermark;
        Log::info('Successfully created EWS subscription: ' . $this->subscriptionId);

        return true;
    }

    protected function streamEvents()
    {
        while ($this->running) {
            try {
                Log::info('Polling for events with watermark: ' . $this->watermark);
                $request = new \jamesiarmes\PhpEws\Request\GetEventsType();
                $request->SubscriptionId = $this->subscriptionId;
                $request->Watermark = $this->watermark;

                $response = $this->client->GetEvents($request);

                $responseMessage = $response->ResponseMessages->GetEventsResponseMessage[0];

                if ($responseMessage->ResponseClass == 'Success') {
                    Log::info('Successfully polled events.');

                    // We only process events and update the watermark if a notification is present.
                    if (isset($responseMessage->Notification)) {
                        // The heartbeat/status update is handled here.
                        if (isset($responseMessage->Notification->StatusEvent[0]->Watermark)) {
                            $this->watermark = $responseMessage->Notification->StatusEvent[0]->Watermark;
                        }

                        // The actual new mail events are processed here.
                        $this->processEvents($responseMessage);
                    }
                } else {
                    // Log when the poll was not successful
                    Log::warning('EWS poll was not successful.', [
                        'ResponseClass' => $responseMessage->ResponseClass,
                        'MessageText' => $responseMessage->MessageText,
                        'ResponseCode' => $responseMessage->ResponseCode,
                    ]);
                }

                // Brief pause before polling again.
                sleep(5);
            } catch (Exception $e) {
                Log::error('Error in EWS event polling: ' . $e->getMessage());

                // If the subscription is no longer valid, try to recreate it.
                if (strpos($e->getMessage(), 'ErrorSubscriptionNotFound') !== false) {
                    Log::info('Subscription not found. Attempting to recreate EWS subscription...');
                    if (! $this->createSubscription()) {
                        // If recreation fails, stop the listener to avoid a loop.
                        $this->running = false;
                        Log::error('Failed to recreate subscription. Stopping listener.');
                    }
                } else {
                    // For other errors, wait before retrying.
                    sleep(15);
                }
            }
        }
    }

    protected function processEvents($responseMessage)
    {
        // From the logs, we know the events are direct children of the Notification object.
        // We prioritize NewMailEvent but fall back to CreatedEvent.
        $events = $responseMessage->Notification->NewMailEvent ?? $responseMessage->Notification->CreatedEvent ?? [];

        if (empty($events)) {
            // No new mail or created events were found in this notification.
            Log::info('No new mail or created events were found in this notification.');

            return;
        }

        // The watermark is updated from the last event in the batch.
        $lastEvent = end($events);
        if (isset($lastEvent->Watermark)) {
            $this->watermark = $lastEvent->Watermark;
            Log::info('Watermark updated from mail event.');
        }

        foreach ($events as $event) {
            Log::info('New mail event detected, processing...');
            // We trigger the mail reader, which will fetch the latest unread emails.
            $this->mailReader->handleApprovals(10);
        }
    }

    protected function unsubscribe()
    {
        if (! $this->subscriptionId) {
            return;
        }

        try {
            $request = new \jamesiarmes\PhpEws\Request\UnsubscribeType();
            $request->SubscriptionId = $this->subscriptionId;

            $this->client->Unsubscribe($request);
            Log::info('Successfully unsubscribed from EWS notifications');

        } catch (Exception $e) {
            Log::error('Error unsubscribing from EWS: ' . $e->getMessage());
        }
    }
}
