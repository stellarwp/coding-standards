<?php
/**
 * ModernTribe_Sniffs_CodeAnalysis_UnusedFunctionParameterSniff
 *
 * This is a shameless copy of the work done by Squizlabs, specifically
 * Greg Sherwood <gsherwood@squiz.net> and Marc McIntyre <mmcintyre@squiz.net>,
 * but modified to match ModernTribe standards.
 *
 * PHP version 5
 *
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    Matthew Batchelder <borkweb@gmail.com>
 * @author    Zachary Tirrell <zbtirrell@gmail.com>
 * @copyright 2012 ModernTribe
 * @license   https://github.com/ModernTribe/ModernTribe-codesniffer/blob/master/licence.txt BSD Licence
 * @link      http://pear.php.net/package/PHP_CodeSniffer
 */

/**
 * Checks the for unused function parameters.
 *
 * This sniff checks that all function parameters are used in the function body.
 * One exception is made for empty function bodies or function bodies that only
 * contain comments. This could be usefull for the classes that implement an
 * interface that defines multiple methods but the implementation only needs some
 * of them.
 *
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    Matthew Batchelder <borkweb@gmail.com>
 * @author    Zachary Tirrell <zbtirrell@gmail.com>
 * @copyright 2012 ModernTribe
 * @license   https://github.com/ModernTribe/ModernTribe-codesniffer/blob/master/licence.txt BSD Licence
 * @version   Release: 1.4.0
 * @link      http://pear.php.net/package/PHP_CodeSniffer
 */
class ModernTribe_Sniffs_CodeAnalysis_UnusedFunctionParameterSniff implements PHP_CodeSniffer_Sniff
{
	/**
	 * Returns an array of tokens this test wants to listen for.
	 *
	 * @return array
	 */
	public function register()
	{
		return array(T_FUNCTION);
	}//end register()

	/**
	 * Processes this test, when one of its tokens is encountered.
	 *
	 * @param PHP_CodeSniffer_File $phpcsFile The file being scanned.
	 * @param int                  $stackPtr  The position of the current token
	 *                                        in the stack passed in $tokens.
	 *
	 * @return void
	 */
	public function process(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
	{
		$tokens = $phpcsFile->getTokens();
		$token  = $tokens[$stackPtr];

		// Skip broken function declarations.
		if (isset($token['scope_opener']) === false || isset($token['parenthesis_opener']) === false) {
			return;
		}

		$params = array();
		foreach ($phpcsFile->getMethodParameters($stackPtr) as $param) {
			$params[$param['name']] = $stackPtr;
		}

		$next = ++$token['scope_opener'];
		$end  = --$token['scope_closer'];

		$foundContent = false;

		for (; $next <= $end; ++$next) {
			$token = $tokens[$next];
			$code  = $token['code'];

			// Ignorable tokens.
			if (in_array($code, PHP_CodeSniffer_Tokens::$emptyTokens) === true) {
				continue;
			}

			if ($foundContent === false) {
				// A throw statement as the first content indicates an interface method.
				if ($code === T_THROW) {
					return;
				}

				// A return statement as the first content indicates an interface method.
				if ($code === T_RETURN) {
					$tmp = $phpcsFile->findNext(PHP_CodeSniffer_Tokens::$emptyTokens, ($next + 1), null, true);
					if ($tmp === false) {
						return;
					}

					// There is a return.
					if ($tokens[$tmp]['code'] === T_SEMICOLON) {
						return;
					}

					$tmp = $phpcsFile->findNext(PHP_CodeSniffer_Tokens::$emptyTokens, ($tmp + 1), null, true);
					if ($tmp !== false && $tokens[$tmp]['code'] === T_SEMICOLON) {
						// There is a return <token>.
						return;
					}
				}//end if
			}//end if

			$foundContent = true;

			if ($code === T_VARIABLE && isset($params[$token['content']]) === true) {
				unset($params[$token['content']]);
			} else if ($code === T_DOLLAR) {
				$nextToken = $phpcsFile->findNext(T_WHITESPACE, ($next + 1), null, true);
				if ($tokens[$nextToken]['code'] === T_OPEN_CURLY_BRACKET) {
					$nextToken = $phpcsFile->findNext(T_WHITESPACE, ($nextToken + 1), null, true);
					if ($tokens[$nextToken]['code'] === T_STRING) {
						$varContent = '$'.$tokens[$nextToken]['content'];
						if (isset($params[$varContent]) === true) {
							unset($params[$varContent]);
						}
					}
				}
			} else if ($code === T_DOUBLE_QUOTED_STRING
				|| $code === T_START_HEREDOC
				|| $code === T_START_NOWDOC
			) {
				// Tokenize strings that can contain variables.
				// Make sure the string is re-joined if it occurs over multiple lines.
				$validTokens = array(
					T_HEREDOC,
					T_NOWDOC,
					T_END_HEREDOC,
					T_END_NOWDOC,
					T_DOUBLE_QUOTED_STRING,
				);
				$validTokens = array_merge($validTokens, PHP_CodeSniffer_Tokens::$emptyTokens);

				$content = $token['content'];
				for ($i = ($next + 1); $i <= $end; $i++) {
					if (in_array($tokens[$i]['code'], $validTokens) === true) {
						$content .= $tokens[$i]['content'];
						$next++;
					} else {
						break;
					}
				}

				$stringTokens = token_get_all(sprintf('<?php %s;?>', $content));
				foreach ($stringTokens as $stringPtr => $stringToken) {
					if (is_array($stringToken) === false) {
						continue;
					}

					$varContent = '';
					if ($stringToken[0] === T_DOLLAR_OPEN_CURLY_BRACES) {
						$varContent = '$'.$stringTokens[($stringPtr + 1)][1];
					} else if ($stringToken[0] === T_VARIABLE) {
						$varContent = $stringToken[1];
					}

					if ($varContent !== '' && isset($params[$varContent]) === true) {
						unset($params[$varContent]);
					}
				}
			}//end if
		}//end for

		if ($foundContent === true && count($params) > 0) {
			foreach ($params as $paramName => $position) {
				if ( '$unused' == substr( $paramName, 0, 7 ) ) {
					continue;
				}
				$error = 'The method parameter %s is never used';
				$data  = array($paramName);
				$phpcsFile->addWarning($error, $position, 'Found', $data);
			}
		}
	}//end process()
}//end class
