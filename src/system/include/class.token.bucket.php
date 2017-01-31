<?php
//
// Token-bucket-component of Laolin's api-core 
// An implementation of the token bucket algorithm.
// By:
//  laolin @ 2017.1.31
//
// algorithm:
//  http://en.wikipedia.org/wiki/Token_bucket#The_token_bucket_algorithm
// php class base:
//  https://github.com/gluxon/tokenbucket/blob/master/TokenBucket.php
//

class TokenBucket {
	private $tokenData;

	public function __construct($da){ // $capacity, $tokens, $fill_rate, $timestamp 
		/* Tokens is the total tokens in the bucket. fill_rate is the
		 * rate in tokens/second that the bucket will be refilled. */
    $this->tokenData=$da;
	}
	public function consume($tokens) {
		/* Consume tokens from the bucket. Returns True if there were
		 * sufficient tokens, otherwise False. */
		if ( $tokens <= $this->tokens() ) {
			$this->tokenData['tokens'] -= $tokens;
			return true;
		}
		return false;
	}
	public function tokens() {
		$now = time();
		if ($this->tokenData['tokens'] < $this->tokenData['capacity'] ) {
			$delta = $this->tokenData['fillRate'] * ($now - $this->tokenData['lastRun']);
			$this->tokenData['tokens'] = min($this->tokenData['capacity'], $this->tokenData['tokens'] + $delta);
		}
		$this->tokenData['lastRun'] = $now;
		return $this->tokenData['tokens'];
	}
	public function data() {
    return $this->tokenData;
  }
}