<?php namespace Laravel; defined('DS') or die('No direct script access.');

class Crypter {

	/**
	 * The encryption cipher.
	 *
	 * @var string
	 */
	//public static $cipher = MCRYPT_RIJNDAEL_256;
	public static $cipher = 'aes-256-cbc';

	/**
	 * The encryption mode.
	 *
	 * @var string
	 */
	//public static $mode = MCRYPT_MODE_CBC;

	/**
	 * The block size of the cipher.
	 *
	 * @var int
	 */
	public static $block = 32;

	/**
	 * Encrypt a string using Mcrypt.
	 *
	 * The string will be encrypted using the AES-256 scheme and will be base64 encoded.
	 *
	 * @param  string  $value
	 * @return string
	 */
	public static function encrypt($value)
	{
		/*
		$iv = mcrypt_create_iv(static::iv_size(), static::randomizer());

		$value = static::pad($value);

		$value = mcrypt_encrypt(static::$cipher, static::key(), $value, static::$mode, $iv);

		return base64_encode($iv.$value);
		*/

		$iv_size = openssl_cipher_iv_length(static::$cipher);
		$iv = openssl_random_pseudo_bytes($iv_size); // Generate a secure random I
		$value = static::pad($value); // Add padding if necessary
		$encrypted = openssl_encrypt($value, static::$cipher, static::key(), OPENSSL_RAW_DATA, $iv);

		return base64_encode($iv . $encrypted);
	}

	/**
	 * Decrypt a string using Mcrypt.
	 *
	 * @param  string  $value
	 * @return string
	 */
	public static function decrypt($value)
	{
		/*
		$value = base64_decode($value);

		// To decrypt the value, we first need to extract the input vector and
		// the encrypted value. The input vector size varies across different
		// encryption ciphers and modes, so we'll get the correct size.
		$iv = substr($value, 0, static::iv_size());

		$value = substr($value, static::iv_size());

		// Once we have the input vector and the value, we can give them both
		// to Mcrypt for decryption. The value is sometimes padded with \0,
		// so we will trim all of the padding characters.
		$key = static::key();

		$value = mcrypt_decrypt(static::$cipher, $key, $value, static::$mode, $iv);

		return static::unpad($value);
		*/

		$iv_size = openssl_cipher_iv_length(static::$cipher);

		// Decode the base64-encoded string
		$decoded = base64_decode($value);

		// Extract the IV and encrypted data
		$iv = substr($decoded, 0, $iv_size);
		$encrypted = substr($decoded, $iv_size);

		// Decrypt the data
		$decrypted = openssl_decrypt($encrypted, static::$cipher, static::key(), OPENSSL_RAW_DATA, $iv);

		// If padding was added manually in encryption, remove it here
		return static::unpad($decrypted);
	}

	/**
	 * Get the most secure random number generator for the system.
	 *
	 * @return int
	 */
	public static function randomizer()
	{
		// There are various sources from which we can get random numbers
		// but some are more random than others. We'll choose the most
		// random source we can for this server environment.
		if (defined('MCRYPT_DEV_URANDOM'))
		{
			return MCRYPT_DEV_URANDOM;
		}
		elseif (defined('MCRYPT_DEV_RANDOM'))
		{
			return MCRYPT_DEV_RANDOM;
		}
		// When using the default random number generator, we'll seed
		// the generator on each call to ensure the results are as
		// random as we can possibly get them.
		else
		{
			mt_srand();

			return MCRYPT_RAND;
		}
	}

	/**
	 * Get the input vector size for the cipher and mode.
	 *
	 * @return int
	 */
	protected static function iv_size()
	{
		return mcrypt_get_iv_size(static::$cipher, static::$mode);
	}

	/**
	 * Add PKCS7 compatible padding on the given value.
	 *
	 * @param  string  $value
	 * @return string
	 */
	protected static function pad($value)
	{
		$pad = static::$block - (Str::length($value) % static::$block);

		return $value .= str_repeat(chr($pad), $pad);
	}

	/**
	 * Remove the PKCS7 compatible padding from the given value.
	 *
	 * @param  string  $value
	 * @return string
	 */
	protected static function unpad($value)
	{
		$pad = ord($value[($length = Str::length($value)) - 1]);

		if ($pad and $pad < static::$block)
		{
			// If the correct padding is present on the string, we will remove
			// it and return the value. Otherwise, we'll throw an exception
			// as the padding appears to have been changed.
			if (preg_match('/'.chr($pad).'{'.$pad.'}$/', $value))
			{
				return substr($value, 0, $length - $pad);
			}

			// If the padding characters do not match the expected padding
			// for the value we'll bomb out with an exception since the
			// encrypted value seems to have been changed.
			else
			{
				throw new \Exception("Decryption error. Padding is invalid.");
			}
		}

		return $value;
	}

	/**
	 * Get the encryption key from the application configuration.
	 *
	 * @return string
	 */
	protected static function key()
	{
		return Config::get('application.key');
	}

}