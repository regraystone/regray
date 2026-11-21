<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The staff assigned to an inquiry. Assignment only adds: a row is never
 * moved to another inquiry or person (a trigger refuses the update), and the
 * app removes none.
 *
 * Each handler plays one stage, read from their roles, the first they hold
 * of sales, technical, accounting and logistics (inquiry_handler_stage()). A
 * trigger refuses, as each row is added:
 *
 * - someone who holds none of them (marketing, the admin, the owner, a client);
 * - on a walk-in (`inquiries.owner_user_id` set), a sales handler other than
 *   its owner;
 * - accounting or logistics before sales has won the inquiry (won, or
 *   for_refund, which only follows won);
 * - a second accounting, or a second logistics, handler on one inquiry.
 *
 * The inquiry's row is locked while the trigger reads it, so two adds at
 * once cannot both take the one accounting or logistics place.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiry_handlers', function (Blueprint $table) {
            $table->foreignId('inquiry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('assigned_at');

            $table->primary(['inquiry_id', 'user_id']);
            $table->index('user_id');
        });

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION inquiry_handler_stage(handler_id bigint) RETURNS text LANGUAGE sql STABLE AS $$
                SELECT role.name::text
                FROM model_has_roles held JOIN roles role ON role.id = held.role_id
                WHERE held.model_id = handler_id AND role.name IN ('sales', 'technical', 'accounting', 'logistics')
                ORDER BY array_position(ARRAY['sales', 'technical', 'accounting', 'logistics']::text[], role.name::text)
                LIMIT 1
            $$
            SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION inquiry_handlers_guard() RETURNS trigger LANGUAGE plpgsql AS $$
            DECLARE
                inquiry record;
                stage text;
            BEGIN
                IF TG_OP = 'UPDATE' THEN
                    IF NEW.inquiry_id IS DISTINCT FROM OLD.inquiry_id OR NEW.user_id IS DISTINCT FROM OLD.user_id THEN
                        RAISE EXCEPTION 'An assignment is never moved to another inquiry or person.' USING ERRCODE = 'check_violation';
                    END IF;

                    RETURN NEW;
                END IF;

                SELECT id, reference, owner_user_id, sales_status INTO inquiry FROM inquiries WHERE id = NEW.inquiry_id FOR UPDATE;
                stage := inquiry_handler_stage(NEW.user_id);

                IF stage IS NULL THEN
                    RAISE EXCEPTION 'Only sales, technical, accounting or logistics staff can be assigned to an inquiry.' USING ERRCODE = 'check_violation';
                END IF;

                IF stage = 'sales' AND inquiry.owner_user_id IS NOT NULL AND NEW.user_id <> inquiry.owner_user_id THEN
                    RAISE EXCEPTION 'Inquiry %''s sales member is its owner.', inquiry.reference USING ERRCODE = 'check_violation';
                END IF;

                IF stage IN ('accounting', 'logistics') THEN
                    IF inquiry.sales_status IS NULL OR inquiry.sales_status NOT IN ('won', 'for_refund') THEN
                        RAISE EXCEPTION 'Inquiry % is not won yet: % joins once sales has won it.', inquiry.reference, stage USING ERRCODE = 'check_violation';
                    END IF;

                    IF EXISTS (
                        SELECT 1 FROM inquiry_handlers other
                        WHERE other.inquiry_id = NEW.inquiry_id AND other.user_id <> NEW.user_id AND inquiry_handler_stage(other.user_id) = stage
                    ) THEN
                        RAISE EXCEPTION 'Inquiry % already has someone from %.', inquiry.reference, stage USING ERRCODE = 'check_violation';
                    END IF;
                END IF;

                RETURN NEW;
            END
            $$
            SQL);

        DB::statement('CREATE TRIGGER trg_inquiry_handlers_guard BEFORE INSERT OR UPDATE ON inquiry_handlers FOR EACH ROW EXECUTE FUNCTION inquiry_handlers_guard()');
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiry_handlers');
        DB::statement('DROP FUNCTION IF EXISTS inquiry_handlers_guard()');
        DB::statement('DROP FUNCTION IF EXISTS inquiry_handler_stage(bigint)');
    }
};
