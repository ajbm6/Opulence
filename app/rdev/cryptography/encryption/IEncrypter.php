<?php
/**
 * Copyright (C) 2015 David Young
 * 
 * Defines the interface for encrypters to implement
 */
namespace RDev\Cryptography\Encryption;

interface IEncrypter 
{
    /**
     * Decrypts data
     *
     * @param string $data The data to decrypt
     * @return string The decrypted data
     * @throws EncryptionException Thrown if there was an error decrypting the data
     */
    public function decrypt($data);

    /**
     * Encrypts data
     *
     * @param string $data The data to encrypt
     * @return string The encrypted data
     * @throws EncryptionException Thrown if there was an error encrypting the data
     */
    public function encrypt($data);

    /**
     * Sets the encryption cipher
     *
     * @param string $cipher The cipher
     */
    public function setCipher($cipher);

    /**
     * Sets the encryption key
     *
     * @param string $key The key
     */
    public function setKey($key);

    /**
     * Sets the encryption mode
     *
     * @param string $mode The mode
     */
    public function setMode($mode);
}