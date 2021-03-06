<?php

namespace AuthBundle\Security;

use Symfony\Component\Security\Core\Encoder\PasswordEncoderInterface;

/**
 * @method bool needsRehash(string $encoded)
 */
class SHA1PasswordEncoder implements PasswordEncoderInterface
{
    /**
     * Encodes the raw password.
     *
     * @param string $raw  The password to encode
     * @param string $salt The salt
     *
     * @return string The encoded password
     */
    public function encodePassword($raw, $salt = null): string
    {
        return "{SHA}" . base64_encode(pack("H*", sha1($raw)));
    }

    /**
     * Checks a raw password against an encoded password.
     *
     * @param string $encoded An encoded password
     * @param string $raw     A raw password
     * @param string $salt    The salt
     *
     * @return bool true if the password is valid, false otherwise
     */
    public function isPasswordValid($encoded, $raw, $salt = null): bool
    {
        return $encoded === $this->encodePassword($raw, $salt);
    }

    public function __call($name, $arguments)
    {
        // TODO: Implement @method bool needsRehash(string $encoded)
    }
}
