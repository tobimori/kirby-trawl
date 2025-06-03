<?php

namespace tobimori\Trawl;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PhpParser\Parser;

class PHPExtractor
{
	private array $translations = [];
	private Parser $parser;

	public function __construct()
	{
		$parserFactory = new ParserFactory();
		$this->parser = $parserFactory->createForNewestSupportedVersion();
	}

	public function extract(string $file): array
	{
		$this->translations = [];

		if (!file_exists($file)) {
			return [];
		}

		$code = file_get_contents($file);

		try {
			$ast = $this->parser->parse($code);

			if ($ast === null) {
				return [];
			}

			$translationsRef = &$this->translations;
			$traverser = new NodeTraverser();
			$visitor = new class($translationsRef) extends NodeVisitorAbstract {
				private $translations;

				public function __construct(&$translations)
				{
					$this->translations = &$translations;
				}

				public function enterNode(Node $node)
				{
					if ($node instanceof Node\Expr\FuncCall && $node->name instanceof Node\Name) {
						$functionName = $node->name->toString();

						// Handle t(), tc(), tt() functions
						if (in_array($functionName, ['t', 'tc', 'tt'], true) && count($node->args) > 0) {
							$firstArg = $node->args[0]->value;

							// Extract string literals
							if ($firstArg instanceof Node\Scalar\String_) {
								$this->translations[] = [
									'key' => $firstArg->value,
									'function' => $functionName,
									'line' => $node->getLine(),
									'context' => $this->getContext($node, $functionName),
								];
							}
							// Handle concatenated strings
							elseif ($firstArg instanceof Node\Expr\BinaryOp\Concat) {
								$concatenated = $this->extractConcatenatedString($firstArg);
								if ($concatenated !== null) {
									$this->translations[] = [
										'key' => $concatenated,
										'function' => $functionName,
										'line' => $node->getLine(),
										'context' => $this->getContext($node, $functionName),
									];
								}
							}
						}
					}
				}

				private function getContext(Node\Expr\FuncCall $node, string $functionName): ?array
				{
					$context = [];

					// For tc(), the second argument is the context/count
					if ($functionName === 'tc' && isset($node->args[1])) {
						$context['plural'] = true;
					}

					// For tt(), extract template parameters
					if ($functionName === 'tt' && isset($node->args[1])) {
						$context['template'] = true;
						// Try to extract template variables from the string
						if (isset($node->args[0]->value) && $node->args[0]->value instanceof Node\Scalar\String_) {
							preg_match_all('/\{(\w+)\}/', $node->args[0]->value->value, $matches);
							if (!empty($matches[1])) {
								$context['variables'] = $matches[1];
							}
						}
					}

					return empty($context) ? null : $context;
				}

				private function extractConcatenatedString(Node $node): ?string
				{
					if ($node instanceof Node\Scalar\String_) {
						return $node->value;
					}

					if ($node instanceof Node\Expr\BinaryOp\Concat) {
						$left = $this->extractConcatenatedString($node->left);
						$right = $this->extractConcatenatedString($node->right);

						if ($left !== null && $right !== null) {
							return $left . $right;
						}
					}

					return null;
				}
			};
			$traverser->addVisitor($visitor);
			$traverser->traverse($ast);
		} catch (\Exception $e) {
			// Log parse errors if needed
		}

		return $this->translations;
	}

	public function extractFromFiles(array $files): array
	{
		$allTranslations = [];

		foreach ($files as $file) {
			$fileTranslations = $this->extract($file);
			foreach ($fileTranslations as $translation) {
				$translation['file'] = $file;
				$allTranslations[] = $translation;
			}
		}

		return $allTranslations;
	}
}
