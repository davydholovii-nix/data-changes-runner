<?php

namespace App\Mob407\V3\Tasks;

use App\Mob407\V3\Helpers\HasLogger;
use App\Mob407\V3\Helpers\HasSources;
use App\Mob407\V3\Tasks\Helpers\JsonReader;
use Illuminate\Database\Capsule\Manager as DB;

class CreateCoulombTablesTask extends AbstractTask
{
    use HasLogger;
    use HasSources;
    use JsonReader;

    public function run(): void
    {
        $this->getOutput()->writeln('Creating coulomb tables...');
        DB::schema()->dropIfExists('external_vehicle_charge');
        $this->createExternalVehicleChargeTable();
        DB::schema()->dropIfExists('external_vehicle_charge_ext');
        $this->createExternalVehicleChargeExtTable();
        DB::schema()->dropIfExists('user_payment_log');
        $this->createUserPaymentLogTable();

    }

    private function createExternalVehicleChargeTable(): void
    {
        DB::connection()
            ->select("
                CREATE TABLE `clb_external_vehicle_charge` (
                    `id` bigint(20) NOT NULL AUTO_INCREMENT,
                    `evse_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                    `rfid_id` int(10) unsigned DEFAULT NULL,
                    `rfid` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                    `serial_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'RfID card serial number',
                    `user_id` int(11) DEFAULT NULL,
                    `card_nick_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                    `charge_session_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                    `transaction_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                    `external_session_start_time` timestamp NULL DEFAULT NULL COMMENT 'session start time from external network CDR',
                    `session_start_time` timestamp NULL DEFAULT NULL,
                    `charging_start_time` timestamp NULL DEFAULT NULL COMMENT 'Time at which the charging process started',
                    `start_offset` decimal(10,0) NOT NULL DEFAULT '0',
                    `session_end_time` timestamp NULL DEFAULT NULL,
                    `charging_end_time` timestamp NULL DEFAULT NULL COMMENT 'Time at which the charging process stopped',
                    `session_duration_secs` int(11) DEFAULT NULL,
                    `charging_time_seconds` int(11) DEFAULT NULL,
                    `fee` decimal(15,2) DEFAULT NULL COMMENT 'total amount in organization currency',
                    `station_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                    `address_line1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                    `address_line2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                    `city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                    `state` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                    `country` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                    `postal_code` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                    `energy` double DEFAULT NULL,
                    `charge_port` smallint(6) DEFAULT NULL,
                    `port_level` tinyint(4) DEFAULT NULL,
                    `session_end_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                    `session_start_medium` enum('SESSION_FROM_MOBILE','SESSION_FROM_STATION') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'SESSION_FROM_MOBILE if session was started from mobile; SESSION_FROM_STATION if session was started using rfid on the station',
                    `outlet_type` tinyint(4) DEFAULT NULL COMMENT '1- Level1, 2- Level2, 3- DC Fast',
                    `evse_lat` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                    `evse_lon` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                    `evse_organization_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                    `network_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                    `timezone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                    `create_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                    `update_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                    `network_id` bigint(20) NOT NULL,
                    `org_id` bigint(20) DEFAULT NULL,
                    `emsp_network_id` bigint(20) DEFAULT NULL,
                    `currency` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'organization currency of the fee',
                    `pricing_processing_status` smallint(6) NOT NULL DEFAULT '0',
                    `billing_time` timestamp NULL DEFAULT NULL,
                    `total_amount_to_user` decimal(20,2) DEFAULT NULL COMMENT 'total amount in user currency',
                    `user_currency` char(3) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'user currency',
                    `conversion_fee` decimal(10,2) DEFAULT NULL COMMENT 'conversion fee percentage',
                    `conversion_fee_amount` decimal(10,2) DEFAULT NULL COMMENT 'conversion fee amount in user currency',
                    `exchange_rate` decimal(10,4) DEFAULT NULL COMMENT 'Exchange rate from station host currency to driver currency. Multiply station host currency by this exchange rate to get driver currency.',
                    `exchange_rate_usd_to_org` decimal(10,4) DEFAULT NULL COMMENT 'exchange rate from usd to organization currency',
                    `pricing_attribute_id` int(11) DEFAULT NULL COMMENT 'pricing attribute id',
                    `external_session_end_time` timestamp NULL DEFAULT NULL COMMENT 'session end time from external network CDR',
                    `is_free_session` tinyint(1) DEFAULT '0',
                    `ghg_saving` decimal(20,6) unsigned NOT NULL DEFAULT '0.000000',
                    `total_tax` decimal(10,4) DEFAULT NULL,
                    `external_pricing_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                    `pricing_policy_id` int(11) unsigned DEFAULT NULL,
                    `pricing_policy_version` int(11) unsigned DEFAULT NULL,
                    `pricing_policy_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                    `pricing_rule_id` int(11) unsigned DEFAULT NULL,
                    `pricing_rule_version` int(11) unsigned DEFAULT NULL,
                    `pricing_rule_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                    `emsp_session_id` int(11) unsigned DEFAULT NULL,
                    `cpo_session_id` varchar(250) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                    `fleet_vehicle_id` int(10) unsigned DEFAULT NULL,
                    `fleet_vehicle_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `fk_clb_ext_vc_sessionid_rfid_idx` (`rfid`,`charge_session_id`),
                    KEY `fk_clb_ext_vehcharge_user_id_idx` (`user_id`),
                    KEY `idx_clb_external_vehicle_charge_user_id` (`user_id`),
                    KEY `evse_billing_time` (`billing_time`)
                ) ENGINE=InnoDB AUTO_INCREMENT=801211415 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
    }

    private function createExternalVehicleChargeExtTable(): void
    {
        DB::connection()
            ->select("
                CREATE TABLE `clb_external_vehicle_charge_ext` (
                    `id` bigint(20) NOT NULL AUTO_INCREMENT,
                    `evc_id` bigint(20) NOT NULL,
                    `start_timezone` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
                    `charging_power` decimal(20,15) DEFAULT NULL,
                    `charging_time` time DEFAULT NULL,
                    `device_id` int(11) NOT NULL,
                    `device_details_id` int(11) DEFAULT NULL,
                    `mac_address` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
                    `connector_id` int(11) DEFAULT NULL,
                    `outlet_number` tinyint(3) unsigned NOT NULL DEFAULT '0',
                    `charging_state_code` varchar(4) COLLATE utf8_unicode_ci DEFAULT NULL,
                    `fault_state_code` varchar(4) COLLATE utf8_unicode_ci DEFAULT NULL,
                    `charge_flag` smallint(1) DEFAULT '0',
                    `summary_state` tinyint(3) unsigned DEFAULT '0',
                    `start_time_synced_flag` tinyint(1) DEFAULT NULL,
                    `end_time_synced_flag` tinyint(1) DEFAULT NULL,
                    `temperature` decimal(20,10) DEFAULT NULL,
                    `alarm_type` varchar(11) COLLATE utf8_unicode_ci DEFAULT NULL,
                    `pricing_dg_name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
                    `nos_share` decimal(10,3) NOT NULL DEFAULT '0.000',
                    `host_share` decimal(10,3) NOT NULL DEFAULT '0.000',
                    `total_amount` decimal(10,3) NOT NULL DEFAULT '0.000',
                    `net_amount` decimal(10,3) NOT NULL DEFAULT '0.000',
                    `discount` decimal(10,3) NOT NULL DEFAULT '0.000' COMMENT 'Amount dicounted from the session. Currently it is the amount deducted for loyality program',
                    `reason_code` smallint(5) unsigned NOT NULL DEFAULT '0',
                    `station_event_id` int(11) DEFAULT NULL,
                    `method_type` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
                    `region_id` int(11) DEFAULT NULL,
                    `software_version` varchar(50) CHARACTER SET latin1 DEFAULT NULL,
                    `device_group_name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
                    `device_serial_number` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
                    `driver_name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
                    `driver_zip` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
                    `vehicle_make` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
                    `vehicle_model` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
                    `vehicle_model_year` int(4) DEFAULT NULL,
                    `rms_voltage` decimal(20,7) DEFAULT NULL COMMENT 'Root mean square voltage AKA Instantaneous voltage',
                    `rms_current` decimal(20,7) DEFAULT NULL COMMENT 'Root mean square current AKA Instantaneous current',
                    `session_maxima_flag` varchar(30) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'flag for host defined maxima for session',
                    `dib_id` int(11) DEFAULT NULL COMMENT 'dib for queued driver session on community station',
                    `session_start_medium` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'SESSION_FROM_STATION signify session start by station SESSION_FROM_CSR signify session start by csr SESSION_FROM_MOBILE signify session start by mobile ALEXA_APP session started via app ALEXA_VOICE_INTERACTION session started via alexa',
                    `convenience_fee` decimal(10,2) NOT NULL DEFAULT '0.00',
                    `is_prime_directive` tinyint(1) DEFAULT '0' COMMENT 'If session is done in an unreachable mode',
                    `input_temp` decimal(20,10) DEFAULT NULL,
                    `output_temp` decimal(20,10) DEFAULT NULL,
                    `system_temp` decimal(20,10) DEFAULT NULL,
                    `ps_id` int(11) DEFAULT NULL,
                    `payment_id` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
                    `ps_customer_id` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
                    `is_double_pump` tinyint(4) DEFAULT NULL,
                    `charging_mode` enum('NRM','SOC','TOC','UNKNOWN') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'UNKNOWN' COMMENT 'charging_mode NRM = normal mode, SOC = state-of-charge mode, TOC = time-of-charge mode, UNKNOWN = No information',
                    `soc` float DEFAULT NULL COMMENT 'state of charge (decimal float percentage: 0.0-100.0%)',
                    `rct` float DEFAULT NULL COMMENT 'remaining charge time (decimal float seconds)',
                    `odometer_time` varchar(40) DEFAULT NULL COMMENT 'last measured time as given by fleet carma',
                    `odometer` float(8,2) DEFAULT NULL COMMENT 'current odometer value',
                    `start_soc` float DEFAULT NULL COMMENT 'state of charge at beginning of the session (decimal float percentage: 0.0-100.0%)',
                    `end_soc` float DEFAULT NULL COMMENT 'state of charge at end of the session (decimal float percentage: 0.0-100.0%)',
                    `pricing_dg_org_name` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
                    `pricing_dg_org_id` varchar(25) COLLATE utf8_unicode_ci DEFAULT NULL,
                    `msg_bits` varchar(3) COLLATE utf8_unicode_ci DEFAULT '000' COMMENT 'firts bits symbolize fss, second bit symbolize last mtr data, third bit symbolize ACD(actively charging duration) has been received or not',
                    `session_stop_medium` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'STOP_SESSION_FROM_STATION signify session start by station STOP_SESSION_FROM_CSR signify session start by csr STOP_SESSION_FROM_MOBILE signify session start by mobile STOP_SESSION_ALEXA_APP session started via app STOP_SESSION_ALEXA_VOICE_INTERACTION session started via alexa',
                    `leaseco_conn_id` int(11) DEFAULT NULL,
                    `leaseco_org_id` int(11) DEFAULT NULL,
                    `leaseco_exchange_rate` decimal(10,4) DEFAULT '1.0000' COMMENT 'Exchange rate from driver currency to the org currency. Multiply driver currency by this exchange rate to get org currency',
                    `business_payment_method_id` int(11) DEFAULT NULL,
                    `transaction_type` enum('PERSONAL','BUSINESS') COLLATE utf8_unicode_ci DEFAULT 'PERSONAL' COMMENT 'type of payment transaction that will happen for the session',
                    `transaction_state` varchar(40) COLLATE utf8_unicode_ci DEFAULT 'CLOSED' COMMENT 'status of the session payment',
                    `host_to_partner_exchange_rate` decimal(10,4) DEFAULT '1.0000' COMMENT 'Exchange rate from station host currency to the org currency. Multiply station host currency by this exchange rate to get org currency',
                    `fps_account_number` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
                    `fleet_id` int(10) unsigned DEFAULT NULL,
                    `license_number` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
                    `business_payment_card_id` int(11) DEFAULT NULL COMMENT 'Card id belongs to card_id in payment_sources table of driver payment service',
                    `fps_expiry_month` tinyint(2) DEFAULT NULL,
                    `fps_expiry_year` int(11) DEFAULT NULL,
                    `card_type` enum('VOYAGER','WEX') COLLATE utf8_unicode_ci DEFAULT NULL,
                    `wex_card_number` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
                    `fee_from_partner` decimal(10,3) DEFAULT NULL,
                    `evse_id_from_hubject` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
                    `suspicious_session` tinyint(1) DEFAULT 0,
                    `voltage` decimal(8,4) DEFAULT NULL COMMENT 'Voltage of the port',
                    `amperage` decimal(8,4) DEFAULT NULL COMMENT 'Amperage of the port',
                    `power_type` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'Power type of the port',
                    `session_router_network` int(11) DEFAULT NULL,
                    `session_status` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'PENDING, ACTIVE, COMPLETED',
                    PRIMARY KEY (`id`),
                    KEY `idx_evc_ext_vc_id` (`evc_id`)
                ) ENGINE=InnoDB AUTO_INCREMENT=7324885 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
            ");
    }

    private function createUserPaymentLogTable(): void
    {
        DB::connection()
            ->select("
                CREATE TABLE `clb_user_payment_log` (
                    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                    `amount` decimal(20,2) DEFAULT '0.00',
                    `account_balance` decimal(20,2) NOT NULL DEFAULT '0.00',
                    `type` smallint(6) NOT NULL COMMENT '1 = auto top, 2 = refunds, 3 = adhoc payment, 4 = initial deposit on signup, 5 = purchase card, 6 = initial deposit on free to paid, 7 = contact less card charging session, 8 = paid registered user session, 9 = reservations, 10 promotional credit, 14 = wex sessions/Fleet Funded session, 15 = fleet account update, 16 = fleet replishnment, 17 = anonymous session, 18=revoke program credit, 19=external session, 20=external session refund',
                    `subtype` tinyint(4) DEFAULT NULL COMMENT 'For refunds (type=2): 1 = account refund, 2 = chargepass card refund, 3 = ad-hoc session refund, 4=registered session refund, 5 = reservation refund, 6 = contactless card session refund, 7 = reservation cancelled, 8 = fleet sessions, 9 = fleet account refund , 10 = anonymous session refund, for revoke credit (type=18) 11 = credit expired, for revoke credit (type=18) 12 = csr revoked credit',
                    `status` smallint(5) unsigned NOT NULL DEFAULT '0' COMMENT '1:success, 0:failure',
                    `transaction_status` tinyint(4) NOT NULL COMMENT '1 = authorized , 2 = captured, 3 = Full Refund , 4 = auth refund, 5 = Partial Refund, 6 = Refund for Card',
                    `user_id` int(11) DEFAULT NULL,
                    `admin_id` int(11) DEFAULT NULL,
                    `user_type` tinyint(4) NOT NULL DEFAULT '1' COMMENT '1= Registered, 2= Ad-hoc, 3 = Fleet, 4 = fleet account',
                    `description` varchar(256) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                    `create_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `vc_id` int(11) DEFAULT NULL,
                    `reservation_id` int(11) DEFAULT NULL,
                    `merchant_id` tinyint(4) DEFAULT NULL COMMENT '1 = Coulomb Account, 2 = Cybersource, 3 = USAePay, 4 = Paypal, 5 =Wex , 6 = Paypal, 7 = Voyager, 8 = CreditCall, 9 = Heartland, 10 = Onramp Credit Card, 11 =  Payter, 12 = GLOBALPAYMENTS',
                    `request_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                    `request_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                    `secured_server_reference_id` int(11) DEFAULT NULL,
                    `logged_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'for keeping track which user has refunded the amount',
                    `is_cron_processed` tinyint(4) NOT NULL DEFAULT '0',
                    `reference_id` int(11) DEFAULT NULL,
                    `total_amount` float(5,3) unsigned DEFAULT NULL,
                    `nos_share` float(5,3) unsigned DEFAULT NULL COMMENT 'refunded nos share amount',
                    `host_share` float(5,3) unsigned DEFAULT NULL COMMENT 'refunded host share amount',
                    `discount` float(5,3) unsigned DEFAULT NULL COMMENT 'refuned discount amount',
                    `account_no` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                    `payment_response_code` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                    `company_id` int(11) DEFAULT NULL,
                    `data` blob,
                    `raw_data` blob,
                    `subnoc_company_id` int(11) DEFAULT NULL,
                    `vendor_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                    `remittance_group_id` int(11) DEFAULT NULL,
                    `organization_currency` char(3) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                    `total_amount_to_user` decimal(20,2) DEFAULT NULL,
                    `user_currency` char(3) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                    `conversion_fee` decimal(10,2) DEFAULT NULL,
                    `conversion_fee_amount` decimal(10,2) DEFAULT NULL,
                    `exchange_rate` decimal(10,4) DEFAULT NULL,
                    `exchange_rate_usd_to_org` decimal(10,4) DEFAULT NULL,
                    `exchange_rate_usd_to_user` decimal(10,4) DEFAULT NULL COMMENT 'Exchange rate from driver currency to USD',
                    `process_payment_timeout` tinyint(3) DEFAULT '0' COMMENT '0: Transaction Completed, 1: Transaction Timeout',
                    `promo_amt` decimal(20,2) NOT NULL DEFAULT '0.00',
                    `reason` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Reason name from clb_promotional_credits_reasons table',
                    `program` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Program name from clb_promotional_credits_programs',
                    `full_description` mediumtext COLLATE utf8mb4_unicode_ci COMMENT 'Promotion Credit full description',
                    `promo_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Promo Code used for issuing credit',
                    `paypal_payer_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                    `last_four_CC` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Last Four CC in case of credit card and last for characters of payer id in case of paypal',
                    `payment_source` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT 'PORTAL',
                    `unique_payment_progress_user` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                    `fleet_id` int(11) DEFAULT NULL,
                    `ps_id` int(11) DEFAULT NULL,
                    `payment_id` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                    `ps_customer_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                    `fleet_service_charge` float(5,2) DEFAULT NULL,
                    `fleet_fee_fixed` float(5,2) DEFAULT NULL,
                    `fleet_fee_percent` float(5,2) DEFAULT NULL,
                    `program_id` int(11) DEFAULT NULL,
                    `promo_code_id` int(11) DEFAULT NULL,
                    `refund_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                    `nos_fee_fixed` float(5,2) DEFAULT NULL,
                    `nos_fee_percent` float(5,2) DEFAULT NULL,
                    `tax` float(5,2) DEFAULT NULL,
                    `redeem_type` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'MONEY' COMMENT 'indicates how the credit is given',
                    `unit` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'MONEY',
                    `is_external` tinyint(1) DEFAULT '0',
                    PRIMARY KEY (`id`),
                    KEY `vc_id_idx` (`vc_id`),
                    KEY `user_id_idx` (`user_id`),
                    KEY `type_idx` (`type`),
                    KEY `subtype_idx` (`subtype`),
                    KEY `status_idx` (`status`),
                    KEY `idx_payment_log_create_date` (`create_date`)
                ) ENGINE=InnoDB AUTO_INCREMENT=46808285 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
    }
}
