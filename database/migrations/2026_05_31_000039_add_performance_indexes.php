<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_messages', function (Blueprint $table) {
            $table->index(['mailbox_address', 'is_quarantined', 'status', 'expires_at'], 'email_messages_inbox_visible_idx');
            $table->index(['mailbox_address', 'received_at'], 'email_messages_inbox_recent_idx');
            $table->index(['intake_source', 'provider_id'], 'email_messages_provider_source_idx');
        });

        Schema::table('email_message_recipients', function (Blueprint $table) {
            $table->index(['email_message_id', 'type'], 'email_recipients_message_type_idx');
            $table->index(['domain', 'type'], 'email_recipients_domain_type_idx');
        });

        Schema::table('email_attachments', function (Blueprint $table) {
            $table->index(['email_message_id', 'status'], 'email_attachments_message_status_idx');
            $table->index(['media_id', 'status'], 'email_attachments_media_status_idx');
        });

        Schema::table('inbound_mail_intakes', function (Blueprint $table) {
            $table->index(['provider', 'status', 'created_at'], 'mail_intakes_provider_status_created_idx');
            $table->index(['provider', 'provider_message_id'], 'mail_intakes_provider_message_idx');
            $table->index(['provider', 'intake_key'], 'mail_intakes_provider_key_idx');
        });

        Schema::table('cleanup_runs', function (Blueprint $table) {
            $table->index(['type', 'status', 'started_at'], 'cleanup_runs_type_status_started_idx');
        });

        Schema::table('abuse_events', function (Blueprint $table) {
            $table->index(['severity', 'status', 'occurred_at'], 'abuse_events_severity_status_time_idx');
        });

        Schema::table('operations_events', function (Blueprint $table) {
            $table->index(['category', 'status', 'occurred_at'], 'operations_category_status_time_idx');
            $table->index(['event_type', 'occurred_at'], 'operations_event_type_time_idx');
        });

        Schema::table('queue_metrics', function (Blueprint $table) {
            $table->index(['queue_name', 'measured_at'], 'queue_metrics_queue_time_idx');
        });

        Schema::table('domains', function (Blueprint $table) {
            $table->index(['status', 'tier', 'priority', 'health_score'], 'domains_pool_selection_idx');
        });

        Schema::table('domain_assignments', function (Blueprint $table) {
            $table->index(['domain_id', 'assigned_at'], 'domain_assignments_domain_time_idx');
            $table->index(['user_id', 'assigned_at'], 'domain_assignments_user_time_idx');
            $table->index(['organization_id', 'assigned_at'], 'domain_assignments_org_time_idx');
        });

        Schema::table('billing_subscriptions', function (Blueprint $table) {
            $table->index(['billing_customer_id', 'status'], 'billing_subs_customer_status_idx');
            $table->index(['status', 'current_period_ends_at'], 'billing_subs_status_period_idx');
        });

        Schema::table('billing_invoices', function (Blueprint $table) {
            $table->index(['billing_customer_id', 'status'], 'billing_invoices_customer_status_idx');
            $table->index(['status', 'issued_at'], 'billing_invoices_status_issued_idx');
        });

        Schema::table('billing_webhook_events', function (Blueprint $table) {
            $table->index(['provider', 'status', 'created_at'], 'billing_webhooks_provider_status_idx');
        });

        Schema::table('domain_health_checks', function (Blueprint $table) {
            $table->index(['domain', 'checked_at'], 'domain_health_domain_time_idx');
            $table->index(['status', 'checked_at'], 'domain_health_status_time_idx');
        });
    }

    public function down(): void
    {
        Schema::table('domain_health_checks', function (Blueprint $table) {
            $table->dropIndex('domain_health_status_time_idx');
            $table->dropIndex('domain_health_domain_time_idx');
        });

        Schema::table('billing_webhook_events', function (Blueprint $table) {
            $table->dropIndex('billing_webhooks_provider_status_idx');
        });

        Schema::table('billing_invoices', function (Blueprint $table) {
            $table->dropIndex('billing_invoices_status_issued_idx');
            $table->dropIndex('billing_invoices_customer_status_idx');
        });

        Schema::table('billing_subscriptions', function (Blueprint $table) {
            $table->dropIndex('billing_subs_status_period_idx');
            $table->dropIndex('billing_subs_customer_status_idx');
        });

        Schema::table('domain_assignments', function (Blueprint $table) {
            $table->dropIndex('domain_assignments_org_time_idx');
            $table->dropIndex('domain_assignments_user_time_idx');
            $table->dropIndex('domain_assignments_domain_time_idx');
        });

        Schema::table('domains', function (Blueprint $table) {
            $table->dropIndex('domains_pool_selection_idx');
        });

        Schema::table('queue_metrics', function (Blueprint $table) {
            $table->dropIndex('queue_metrics_queue_time_idx');
        });

        Schema::table('operations_events', function (Blueprint $table) {
            $table->dropIndex('operations_event_type_time_idx');
            $table->dropIndex('operations_category_status_time_idx');
        });

        Schema::table('abuse_events', function (Blueprint $table) {
            $table->dropIndex('abuse_events_severity_status_time_idx');
        });

        Schema::table('cleanup_runs', function (Blueprint $table) {
            $table->dropIndex('cleanup_runs_type_status_started_idx');
        });

        Schema::table('inbound_mail_intakes', function (Blueprint $table) {
            $table->dropIndex('mail_intakes_provider_key_idx');
            $table->dropIndex('mail_intakes_provider_message_idx');
            $table->dropIndex('mail_intakes_provider_status_created_idx');
        });

        Schema::table('email_attachments', function (Blueprint $table) {
            $table->dropIndex('email_attachments_media_status_idx');
            $table->dropIndex('email_attachments_message_status_idx');
        });

        Schema::table('email_message_recipients', function (Blueprint $table) {
            $table->dropIndex('email_recipients_domain_type_idx');
            $table->dropIndex('email_recipients_message_type_idx');
        });

        Schema::table('email_messages', function (Blueprint $table) {
            $table->dropIndex('email_messages_provider_source_idx');
            $table->dropIndex('email_messages_inbox_recent_idx');
            $table->dropIndex('email_messages_inbox_visible_idx');
        });
    }
};
