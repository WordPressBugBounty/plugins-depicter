<?php

namespace Depicter\Services;

use Averta\WordPress\Utility\JSON;
use Depicter;

class QueueService
{
    const CRON_HOOK = 'depicter/queue/cron';

    public function __construct() {
        add_action( 'init', [ $this, 'schedule_queue_table' ] );
        add_filter( 'cron_schedules', [ $this, 'cron_schedules' ] );
        add_action( self::CRON_HOOK, [ $this, 'process' ] );
    }

    public function cron_schedules( $schedules ) {
        $schedules['every_minute'] = [
            'interval' => 60,
            'display'  => __( 'Every Minute', 'depicter' )
        ];

        return $schedules;
    }

    public function schedule_queue_table() {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time(), 'every_minute', self::CRON_HOOK );
        }
    }
    public function process() {
        $jobs = \Depicter::queueJobsRepository()->job()->where('status', 'in', ['pending', 'failed'])->take(5)->get();
        if ( ! $jobs ) {
            return;
        }

        $jobs = $jobs->toArray();
        foreach ( $jobs as $job ) {
            \Depicter::queueJobsRepository()->update( $job['id'], [
                'status' => 'processing',
            ] );

            $success = false;
            $last_error = '';
            $payload = JSON::decode($job['payload'], true);
            if ( $job['queue'] == 'mailchimp' ) {
                $response = \Depicter::integration()->mailchimp()->submitToMailchimp( $payload['lead_id'] );
                $success = $response['success'];
                $last_error = $success ? '' : $response['error'];
            }

            if ( $success ) {
                \Depicter::queueJobsRepository()->update( $job['id'], [
                    'status' => 'completed',
                ] );
            } else {
                \Depicter::queueJobsRepository()->update( $job['id'], [
                    'status' => 'failed',
                    'attempts' => $job['attempts'] + 1,
                    'last_error'  => $last_error,
                ]);
            }
        }
    }


}
