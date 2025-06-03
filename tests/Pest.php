<?php

// Custom expectations
expect()->extend('toBeOne', function () {
	return $this->toBe(1);
});
