<?php
namespace Depicter\Integration;

use Averta\WordPress\Utility\JSON;
use Depicter\Integration\MailChimp\MailChimp;

class Manager {

    public function init() {
        add_action( 'depicter/lead/created', [ $this, 'add_queue_job' ], 10, 3 );
    }

    /**
     * @return MailChimp
     */
    public function mailchimp(): MailChimp {
        return \Depicter::resolve('depicter.integration.mailchimp');
    }

    public function add_queue_job( $lead_id, $source_id, $content_id ) {
        if ( false !== $config = $this->mailchimp()->getConfig( $source_id, $content_id ) ) {
            $payload = JSON::encode([
                'lead_id' => $lead_id,
            ]);

            \Depicter::queueJobsRepository()->create( 'mailchimp',$payload );
        }
    }
}
