<?php

declare(strict_types=1);

namespace Kitchen\Logging;

/**
 * Stable event IDs for every executable action. IDs never change meaning; new actions get new numbers.
 * 1xxx request/runtime, 2xxx sign-in, 3xxx catalog/tables, 4xxx tickets, 5xxx orders,
 * 6xxx payments, 7xxx service calls, 8xxx administration, 9xxx command line.
 */
enum EventId: int
{
    case RouteNotFound = 1000;
    case RequestInvalid = 1001;
    case CsrfFailed = 1002;
    case PhpWarning = 1010;
    case FatalError = 1011;

    case Session = 2000;
    case LoginSucceeded = 2001;
    case LoginFailed = 2002;
    case LoginLocked = 2003;
    case Logout = 2010;

    case MenuList = 3000;
    case TableList = 3001;
    case TableStatus = 3100;
    case TableBill = 3101;

    case TicketList = 4000;
    case TicketStatusChange = 4001;
    case StationAlertAck = 4002;

    case OrderCreate = 5000;
    case OrderChange = 5001;
    case OrderCancel = 5002;

    case PaymentList = 6000;
    case TablePaid = 6001;
    case OrderPaid = 6002;
    case PaymentReverse = 6003;

    case ServiceCallList = 7000;
    case ServiceCallCreate = 7001;
    case ServiceCallAck = 7002;
    case ServiceCallCancel = 7003;

    case AdminUserList = 8000;
    case AdminUserCreate = 8001;
    case AdminUserUpdate = 8002;
    case AdminUserStatus = 8003;
    case AdminMenuSave = 8010;
    case AdminMenuStatus = 8011;
    case AdminDrinkSave = 8012;
    case AdminTableSave = 8020;
    case AdminTableStatus = 8021;

    case Migration = 9000;
    case CreateAdmin = 9010;
    case EnvironmentCheck = 9020;
    case CliFailed = 9099;

    /** Event of an API request; an action called with the wrong method is a missing route, as in the Kernel. */
    public static function forRequest(string $action, string $method): self
    {
        return match ($method.' '.$action) {
            'GET session' => self::Session,
            'POST login' => self::LoginSucceeded,
            'POST logout' => self::Logout,
            'GET menu' => self::MenuList,
            'GET tables' => self::TableList,
            'GET table_status' => self::TableStatus,
            'GET table_bill' => self::TableBill,
            'GET tickets' => self::TicketList,
            'POST status' => self::TicketStatusChange,
            'POST station_alert_ack' => self::StationAlertAck,
            'POST order' => self::OrderCreate,
            'POST order_change' => self::OrderChange,
            'POST order_cancel' => self::OrderCancel,
            'GET payments' => self::PaymentList,
            'POST table_paid' => self::TablePaid,
            'POST order_paid' => self::OrderPaid,
            'POST payment_reverse' => self::PaymentReverse,
            'GET service_calls' => self::ServiceCallList,
            'POST service_call' => self::ServiceCallCreate,
            'POST service_call_ack' => self::ServiceCallAck,
            'POST service_call_cancel' => self::ServiceCallCancel,
            'GET admin_users' => self::AdminUserList,
            'POST admin_user' => self::AdminUserCreate,
            'POST admin_user_update' => self::AdminUserUpdate,
            'POST admin_user_status' => self::AdminUserStatus,
            'POST admin_menu' => self::AdminMenuSave,
            'POST admin_menu_status' => self::AdminMenuStatus,
            'POST admin_drink' => self::AdminDrinkSave,
            'POST admin_table' => self::AdminTableSave,
            'POST admin_table_status' => self::AdminTableStatus,
            default => self::RouteNotFound,
        };
    }
}
