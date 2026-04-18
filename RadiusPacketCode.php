<?php

/**
 * RADIUS Packet Type Code Constants
 * 
 * Based on RFC 2865 and RFC 2866.
 * These constants represent the packet type codes used in the RADIUS protocol.
 * 
 * Usage:
 *   return [RadiusPacketCode::ACCESS_ACCEPT, [ ... ]];
 *   return [RadiusPacketCode::ACCESS_REJECT, [ ... ]];
 */
class RadiusPacketCode
{
    /** Code 1 - Sent by NAS to request authentication */
    const ACCESS_REQUEST        = 1;

    /** Code 2 - Authentication successful */
    const ACCESS_ACCEPT         = 2;

    /** Code 3 - Authentication failed / denied */
    const ACCESS_REJECT         = 3;

    /** Code 4 - Sent by NAS for accounting (start, stop, interim) */
    const ACCOUNTING_REQUEST    = 4;

    /** Code 5 - Server acknowledges the accounting request */
    const ACCOUNTING_RESPONSE   = 5;

    /** Code 11 - Additional information needed (e.g., OTP, MFA) */
    const ACCESS_CHALLENGE      = 11;

    /** Code 12 - Server status inquiry (experimental) */
    const STATUS_SERVER         = 12;

    /** Code 13 - Client status inquiry (experimental) */
    const STATUS_CLIENT         = 13;

    /** Code 40 - Disconnect-Request (RFC 5176 / CoA) */
    const DISCONNECT_REQUEST    = 40;

    /** Code 41 - Disconnect-ACK */
    const DISCONNECT_ACK        = 41;

    /** Code 42 - Disconnect-NAK */
    const DISCONNECT_NAK        = 42;

    /** Code 43 - CoA-Request (RFC 5176) */
    const COA_REQUEST           = 43;

    /** Code 44 - CoA-ACK */
    const COA_ACK               = 44;

    /** Code 45 - CoA-NAK */
    const COA_NAK               = 45;

    /** Code 255 - Reserved */
    const RESERVED              = 255;

    /**
     * Human-readable names for each packet code.
     */
    const NAMES = [
        self::ACCESS_REQUEST        => 'Access-Request',
        self::ACCESS_ACCEPT         => 'Access-Accept',
        self::ACCESS_REJECT         => 'Access-Reject',
        self::ACCOUNTING_REQUEST    => 'Accounting-Request',
        self::ACCOUNTING_RESPONSE   => 'Accounting-Response',
        self::ACCESS_CHALLENGE      => 'Access-Challenge',
        self::STATUS_SERVER         => 'Status-Server (experimental)',
        self::STATUS_CLIENT         => 'Status-Client (experimental)',
        self::DISCONNECT_REQUEST    => 'Disconnect-Request',
        self::DISCONNECT_ACK        => 'Disconnect-ACK',
        self::DISCONNECT_NAK        => 'Disconnect-NAK',
        self::COA_REQUEST           => 'CoA-Request',
        self::COA_ACK               => 'CoA-ACK',
        self::COA_NAK               => 'CoA-NAK',
        self::RESERVED              => 'Reserved',
    ];

    /**
     * Get the human-readable name for a packet code.
     *
     * @param int $code
     * @return string
     */
    public static function getName(int $code): string
    {
        return self::NAMES[$code] ?? 'Unknown (' . $code . ')';
    }
}
