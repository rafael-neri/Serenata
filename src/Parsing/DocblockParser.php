<?php

namespace Serenata\Parsing;

use DomainException;
use InvalidArgumentException;

use League\HTMLToMarkdown\HtmlConverterInterface;

use Serenata\Analysis\DocblockAnalyzer;

use Serenata\Parsing\DocblockTypeParserInterface;

/**
 * Parser for PHP docblocks.
 */
final class DocblockParser
{
    /**
     * PSR-5 and/or phpDocumentor docblock tags.
     *
     * @var string
     */
    public const VAR_TYPE        = '@var';

    /**
     * @var string
     */
    public const PARAM_TYPE      = '@param';

    /**
     * @var string
     */
    public const THROWS          = '@throws';

    /**
     * @var string
     */
    public const RETURN_VALUE    = '@return';

    /**
     * @var string
     */
    public const DEPRECATED      = '@deprecated';

    /**
     * @var string
     */
    public const METHOD          = "@method";

    /**
     * @var string
     */
    public const PROPERTY        = '@property';

    /**
     * @var string
     */
    public const PROPERTY_READ   = '@property-read';

    /**
     * @var string
     */
    public const PROPERTY_WRITE  = '@property-write';

    /**
     * @var string
     */
    public const CATEGORY        = '@category';

    /**
     * @var string
     */
    public const SUBPACKAGE      = '@subpackage';

    /**
     * @var string
     */
    public const LINK            = '@link';

    /**
     * @var string
     */
    public const DESCRIPTION     = 'description';

    /**
     * @var string
     */
    public const INHERITDOC      = '{@inheritDoc}';

    /**
     * Non-standard tags.
     *
     * @var string
     */
    public const ANNOTATION      = '@Annotation';

    /**
     * @var string
     */
    public const TYPE_SPLITTER   = '|';

    /**
     * @var string
     */
    protected const TAG_START_REGEX = '/^(\@.+)(?:\*\/)?$/';

    /**
     * @var DocblockAnalyzer
     */
    private $docblockAnalyzer;

    /**
     * @var DocblockTypeParserInterface
     */
    private $docblockTypeParser;

    /**
     * @var HtmlConverterInterface
     */
    private $htmlToMarkdownConverter;

    /**
     * @param DocblockAnalyzer            $docblockAnalyzer
     * @param DocblockTypeParserInterface $docblockTypeParser
     * @param HtmlConverterInterface      $htmlToMarkdownConverter
     */
    public function __construct(
        DocblockAnalyzer $docblockAnalyzer,
        DocblockTypeParserInterface $docblockTypeParser,
        HtmlConverterInterface $htmlToMarkdownConverter
    ) {
        $this->docblockAnalyzer = $docblockAnalyzer;
        $this->docblockTypeParser = $docblockTypeParser;
        $this->htmlToMarkdownConverter = $htmlToMarkdownConverter;
    }

    /**
     * Parse the comment string to get its elements.
     *
     * @param string|false|null $docblock The docblock to parse. If null, the return array will be filled up with the
     *                                    correct keys, but they will be empty.
     * @param string[]          $filters  Elements to search (see constants).
     * @param string            $itemName The name of the item (method, class, ...) the docblock is for.
     *
     * @return array<string,mixed>
     */
    public function parse($docblock, array $filters, string $itemName): array
    {
        if ($filters === []) {
            return [];
        }

        $tags = [];
        $matches = [];

        $docblock = is_string($docblock) ? $docblock : null;

        if ($docblock !== null && $docblock !== '') {
            $docblock = $this->normalizeDocblock($docblock);

            if ($docblock !== '') {
                preg_match_all('/^@[a-zA-Z0-9-\\\\]+/m', $docblock, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

                $segments = [];
                $previousMatch = null;

                // Build a list of 'segments', which are just a collection of ranges indicating where each detected tag
                // starts and stops.
                foreach ($matches as $match) {
                    $segments[] = [$previousMatch[0][0] ?? null, $previousMatch[0][1] ?? 0, $match[0][1]];

                    $previousMatch = $match;
                }

                // NOTE: preg_match_all returns byte offsets, not character offsets.
                $segments[] = [$previousMatch[0][0] ?? null, $previousMatch[0][1] ?? 0, strlen($docblock)];

                foreach ($segments as $segment) {
                    [$tag, $start, $end] = $segment;

                    if ($tag === null) {
                        continue;
                    }

                    assert(is_string($tag), 'Expected tag to be string');

                    if ($tag === '') {
                        continue;
                    } elseif (!isset($tags[$tag])) {
                        $tags[$tag] = [];
                    }

                    $tags[$tag][] = $this->sanitizeText(
                        substr(
                            substr($docblock, $start, $end - $start),
                            strlen($tag)
                        )
                    );
                }
            }
        }

        $result = [];

        foreach ($filters as $filter) {
            $methodResult = [];

            if ($filter === static::RETURN_VALUE) {
                $methodResult = $this->filterReturn($docblock, $itemName, $tags);
            } elseif ($filter === static::PARAM_TYPE) {
                $methodResult = $this->filterParams($docblock, $itemName, $tags);
            } elseif ($filter === static::VAR_TYPE) {
                $methodResult = $this->filterVar($docblock, $itemName, $tags);
            } elseif ($filter === static::DEPRECATED) {
                $methodResult = $this->filterDeprecated($docblock, $itemName, $tags);
            } elseif ($filter === static::THROWS) {
                $methodResult = $this->filterThrows($docblock, $itemName, $tags);
            } elseif ($filter === static::DESCRIPTION) {
                $methodResult = $this->filterDescription($docblock, $itemName, $tags);
            } elseif ($filter === static::METHOD) {
                $methodResult = $this->filterMethod($docblock, $itemName, $tags);
            } elseif ($filter === static::PROPERTY) {
                $methodResult = $this->filterProperty($docblock, $itemName, $tags);
            } elseif ($filter === static::PROPERTY_READ) {
                $methodResult = $this->filterPropertyRead($docblock, $itemName, $tags);
            } elseif ($filter === static::PROPERTY_WRITE) {
                $methodResult = $this->filterPropertyWrite($docblock, $itemName, $tags);
            } elseif ($filter === static::CATEGORY) {
                $methodResult = $this->filterCategory($docblock, $itemName, $tags);
            } elseif ($filter === static::SUBPACKAGE) {
                $methodResult = $this->filterSubpackage($docblock, $itemName, $tags);
            } elseif ($filter === static::LINK) {
                $methodResult = $this->filterLink($docblock, $itemName, $tags);
            } elseif ($filter === static::ANNOTATION) {
                $methodResult = $this->filterAnnotation($docblock, $itemName, $tags);
            } else {
                throw new DomainException('Unknown filter "' . $filter . '" passed');
            }

            $result = array_merge($result, $methodResult);
        }

        return $result;
    }

    /**
     * @param string $docblock
     *
     * @return string
     */
    private function normalizeDocblock(string $docblock): string
    {
        $docblock = trim($docblock);

        $docblock = $this->stripDocblockStartDelimiter($docblock);
        $docblock = $this->stripDocblockEndDelimiter($docblock);
        $docblock = $this->stripDocblockLineDelimiters($docblock);
        $docblock = $this->normalizeNewlines($docblock);

        return trim($docblock);
    }

    /**
     * @param string $docblock
     *
     * @return string
     */
    private function stripDocblockStartDelimiter(string $docblock): string
    {
        return mb_substr($docblock, 2);
    }

    /**
     * @param string $docblock
     *
     * @return string
     */
    private function stripDocblockEndDelimiter(string $docblock): string
    {
        return mb_substr($docblock, 0, -2);
    }

    /**
     * @param string $docblock
     *
     * @return string
     */
    private function stripDocblockLineDelimiters(string $docblock): string
    {
        $result = preg_replace('/^[\t ]*\**[\t ]{0,1}/m', '', $docblock);

        return $result !== null ? $result : '';
    }

    /**
     * Indicates if the specified tag is valid. Tags should be lower-case.
     *
     * @param string $tag The tag, without the @ sign.
     *
     * @return bool
     */
    public function isValidTag(string $tag): bool
    {
        return in_array($tag, [
            // PHPDOC tags, see also https://phpdoc.org/docs/latest/index.html .
            'api',
            'author',
            'category',
            'copyright',
            'deprecated',
            'example',
            'filesource',
            'global',
            'ignore',
            'internal',
            'license',
            'link',
            'method',
            'package',
            'param',
            'property',
            'property-read',
            'property-write',
            'return',
            'see',
            'since',
            'source',
            'subpackage',
            'throws',
            'todo',
            'uses',
            'var',
            'version',

            'inheritdoc',
            'inheritDoc',

            // PHPUnit tags, see also https://phpunit.de/manual/current/en/appendixes.annotations.html .
            'author',
            'after',
            'afterClass',
            'backupGlobals',
            'backupStaticAttributes',
            'before',
            'beforeClass',
            'codeCoverageIgnore',
            'codeCoverageIgnoreStart',
            'codeCoverageIgnoreEnd',
            'covers',
            'coversDefaultClass',
            'coversNothing',
            'dataProvider',
            'depends',
            'expectedException',
            'expectedExceptionCode',
            'expectedExceptionMessage',
            'expectedExceptionMessageRegExp',
            'group',
            'large',
            'medium',
            'preserveGlobalState',
            'requires',
            'runTestsInSeparateProcesses',
            'runInSeparateProcess',
            'small',
            'test',
            'testdox',
            'ticket',
            'uses',

            // Doctrine annotation tags, see also
            // http://doctrine-common.readthedocs.io/en/latest/reference/annotations.html .
            'Annotation',
            'Target',
            'Enum',
            'IgnoreAnnotation',
            'Required',
            'Attribute',
            'Attributes',

            // PHPMD tags, see also https://phpmd.org/documentation/suppress-warnings.html
            'SuppressWarnings',

            // PhpStorm tags
            'noinspection',
        ], true);
    }

    /**
     * Returns an array of $partCount values, the first value will go up until the first space, the second value will
     * go up until the second space, and so on. The last value will contain the rest of the string. Convenience method
     * for tags that consist of multiple parameters. This method returns an array with guaranteed $partCount elements.
     *
     * @param string $value
     * @param int    $partCount
     *
     * @return mixed[]
     */
    private function filterParameterTag(string $value, int $partCount): array
    {
        $segments = [];
        $parts = preg_split('/[\t ]+/', $value);

        assert($parts !== false);

        while ($partCount--) {
            if ($parts !== []) {
                $segments[] = array_shift($parts);
            } else {
                $segments[] = null;
            }
        }

        // Append the remaining text to the last element.
        if ($parts !== []) {
            $segments[count($segments) - 1] .= ' ' . implode(' ', $parts);
        }

        return $segments;
    }

    /**
     * Filters out information about the return value of the function or method.
     *
     * @param string|null         $docblock
     * @param string              $itemName
     * @param array<string,mixed> $tags
     *
     * phpcs:disable
     *
     * @return array<string,mixed>
     */
    private function filterReturn(?string $docblock, string $itemName, array $tags): array
    {
        // phpcs:enable
        $return = null;

        if (isset($tags[static::RETURN_VALUE])) {
            [$type, $description] = $this->filterParameterTag($tags[static::RETURN_VALUE][0], 2);

            if ($type !== null && $type !== '') {
                $return = [
                    'type'        => $this->docblockTypeParser->parse($this->sanitizeText($type)),
                    'description' => $description !== null ? $this->convertHtmlToMarkdown($description) : null,
                ];
            }
        } elseif ($docblock !== null) {
            // According to https://www.phpdoc.org/docs/latest/references/phpdoc/tags/return.html, a method that does
            // have a docblock, but no explicit return type returns void. Constructors, however, must return self. If
            // there is no docblock at all, we can't assume either of these types.
            $return = [
                'type'        => $this->docblockTypeParser->parse(($itemName === '__construct') ? 'self' : 'void'),
                'description' => null,
            ];
        }

        return [
            'return' => $return,
        ];
    }

    /**
     * Filters out information about the parameters of the function or method.
     *
     * @param string|null         $docblock
     * @param string              $itemName
     * @param array<string,mixed> $tags
     *
     * phpcs:disable
     *
     * @return array<string,mixed>
     */
    private function filterParams(?string $docblock, string $itemName, array $tags): array
    {
        // phpcs:enable
        $params = [];

        if (isset($tags[static::PARAM_TYPE])) {
            foreach ($tags[static::PARAM_TYPE] as $tag) {
                [$type, $variableName, $description] = $this->filterParameterTag($tag, 3);

                if ($type === '' || $type === null || $variableName === null || $variableName === '') {
                    continue;
                }

                $type = $this->sanitizeText($type);
                $variableName = $this->sanitizeText($variableName);

                $isVariadic = false;
                $isReference = false;

                if (mb_strpos($variableName, '...') === 0) {
                    $isVariadic = true;
                    $variableName = mb_substr($variableName, mb_strlen('...'));
                }

                if (mb_strpos($variableName, '&') === 0) {
                    $isReference = true;
                    $variableName = mb_substr($variableName, mb_strlen('&'));
                }

                $params[$variableName] = [
                    'type'        => $this->docblockTypeParser->parse($type),
                    'description' => $description !== null ? $this->convertHtmlToMarkdown($description) : null,
                    'isVariadic'  => $isVariadic,
                    'isReference' => $isReference,
                ];
            }
        }

        return [
            'params' => $params,
        ];
    }

    /**
     * Filters out information about the variable.
     *
     * @param string|null         $docblock
     * @param string              $itemName
     * @param array<string,mixed> $tags
     *
     * phpcs:disable
     *
     * @return array<string,mixed>
     */
    private function filterVar(?string $docblock, string $itemName, array $tags): array
    {
        // phpcs:enable
        $vars = [];

        if (isset($tags[static::VAR_TYPE])) {
            foreach ($tags[static::VAR_TYPE] as $tag) {
                [$varType, $varName, $varDescription] = $this->filterParameterTag($tag, 3);

                if ($varType === null || $varType === '') {
                    continue;
                }

                $varType = $this->sanitizeText($varType);

                $type = $this->docblockTypeParser->parse($varType);

                if ($varName !== null && $varName !== '') {
                    $varName = $this->sanitizeText($varName);

                    if (mb_substr($varName, 0, 1) === '$') {
                        // Example: "@var DateTime $foo My description". The tag includes the name of the property it
                        // documents, it must match the property we're fetching documentation about.
                        $vars[$varName] = [
                            'type'        => $type,
                            'description' => $varDescription !== null ?
                                $this->convertHtmlToMarkdown($varDescription) : null,
                        ];
                    } else {
                        // Example: "@var DateTime My description".
                        $vars['$' . $itemName] = [
                            'type'        => $type,
                            'description' => $this->convertHtmlToMarkdown(trim($varName . ' ' . $varDescription)),
                        ];
                    }
                } elseif ($varDescription === null || $varDescription === '') {
                    // Example: "@var DateTime".
                    $vars['$' . $itemName] = [
                        'type'        => $type,
                        'description' => null,
                    ];
                }
            }
        }

        return [
            'var' => $vars,
        ];
    }

    /**
     * Filters out deprecation information.
     *
     * @param string|null         $docblock
     * @param string              $itemName
     * @param array<string,mixed> $tags
     *
     * phpcs:disable
     *
     * @return array<string,mixed>
     */
    private function filterDeprecated(?string $docblock, string $itemName, array $tags): array
    {
        // phpcs:enable
        return [
            'deprecated' => isset($tags[static::DEPRECATED]),
        ];
    }

    /**
     * Filters out information about what exceptions the method can throw.
     *
     * @param string|null         $docblock
     * @param string              $itemName
     * @param array<string,mixed> $tags
     *
     * phpcs:disable
     *
     * @return array<string,mixed>
     */
    private function filterThrows(?string $docblock, string $itemName, array $tags): array
    {
        // phpcs:enable
        $throws = [];

        if (isset($tags[static::THROWS])) {
            foreach ($tags[static::THROWS] as $tag) {
                [$type, $description] = $this->filterParameterTag($tag, 2);

                if ($type !== null && $type !== '') {
                    $throws[] = [
                        'type'        => $this->docblockTypeParser->parse($this->sanitizeText($type)),
                        'description' => $description !== null ? $this->convertHtmlToMarkdown($description) : null,
                    ];
                }
            }
        }

        return [
            'throws' => $throws,
        ];
    }

    /**
     * Filters out information about the magic methods of a class.
     *
     * @param string|null         $docblock
     * @param string              $itemName
     * @param array<string,mixed> $tags
     *
     * phpcs:disable
     *
     * @return array<string,mixed>
     */
    private function filterMethod(?string $docblock, string $itemName, array $tags): array
    {
        // phpcs:enable
        $methods = [];

        $type = null;
        $description = null;

        if (isset($tags[static::METHOD])) {
            foreach ($tags[static::METHOD] as $tag) {
                // The method signature can contain spaces, so we can't use a simple filterParameterTag.
                if (preg_match(
                    '/^(static\s+)?(?:(\S+)\s+)?([A-Za-z0-9_]+\(.*\))(?:\s+(.+))?$/',
                    $tag,
                    $match
                ) !== false) {
                    $partCount = count($match);
                    $methodSignature = null;

                    if ($partCount === 5) {
                        $type = $match[2] !== null && $match[2] !== '' ? $match[2] : 'void';
                        $methodSignature = $match[3];
                        $description = $match[4];
                    } elseif ($partCount === 4) {
                        if (!$match[2]) {
                            $type = 'void';
                            $methodSignature = $match[3];
                            $description = null;
                        } elseif (mb_strpos($match[2], '(') === false) {
                            // The description was omitted.
                            $type = $match[2];
                            $methodSignature = $match[3];
                            $description = null;
                        }
                    } else {
                        continue; // Empty @method tag, skip it.
                    }

                    $isStatic = (trim($match[1]) === 'static');

                    $requiredParameters = [];
                    $optionalParameters = [];

                    if (preg_match('/^([A-Za-z0-9_]+)\((.*)\)$/', $methodSignature, $match) !== false) {
                        $methodName = $match[1];
                        $methodParameterList = $match[2];

                        // NOTE: Example string: "$param1, int $param2, $param3 = array(), SOME\\TYPE_1 $param4 = null".
                        preg_match_all(
                            '/(?:(\\\\?[a-zA-Z_][a-zA-Z0-9_]*(?:\\\\[a-zA-Z_][a-zA-Z0-9_]*)*)\s+)?(\$[A-Za-z0-9_]+)(?:\s*=\s*([^,]+))?(?:,|$)/',
                            $methodParameterList,
                            $matches,
                            PREG_SET_ORDER
                        );

                        foreach ($matches as $parameterMatch) {
                            $parameterName = null;
                            $parameterType = null;
                            $defaultValue = null;
                            $partCount = count($parameterMatch);

                            if ($partCount === 4) {
                                $parameterType = $parameterMatch[1] !== '' ? $parameterMatch[1] : null;
                                $parameterName = $parameterMatch[2];
                                $defaultValue = $parameterMatch[3];
                            } elseif ($partCount === 3) {
                                $parameterType = $parameterMatch[1] !== '' ? $parameterMatch[1] : null;
                                $parameterName = $parameterMatch[2];
                                $defaultValue = null;
                            }

                            $data = [
                                'type' => $parameterType !== null ?
                                    $this->docblockTypeParser->parse($parameterType) :
                                    null,

                                'defaultValue' => $defaultValue,
                            ];

                            if ($defaultValue === '' || $defaultValue === null) {
                                $requiredParameters[$parameterName] = $data;
                            } else {
                                $optionalParameters[$parameterName] = $data;
                            }
                        }
                    } else {
                        continue; // Invalid method signature.
                    }

                    $methods[$methodName] = [
                        'type'                => $type !== null ? $this->docblockTypeParser->parse($type) : null,
                        'isStatic'            => $isStatic,
                        'requiredParameters'  => $requiredParameters,
                        'optionalParameters'  => $optionalParameters,

                        'description' => $description !== null ?
                            $this->convertHtmlToMarkdown($description) : null,
                    ];
                }
            }
        }

        return [
            'methods' => $methods,
        ];
    }

    /**
     * Filters out information about the magic properties of a class.
     *
     * @param string              $tagName
     * @param string              $keyName
     * @param string|null         $docblock
     * @param string              $itemName
     * @param array<string,mixed> $tags
     *
     * @return array<string,mixed>
     */
    private function filterPropertyTag(
        string $tagName,
        string $keyName,
        ?string $docblock,
        string $itemName,
        array $tags
    ): array {
        $properties = [];

        if (isset($tags[$tagName])) {
            foreach ($tags[$tagName] as $tag) {
                [$staticKeyword, $type, $variableName, $description] = $this->filterParameterTag($tag, 4);

                // Normally, this tag consists of three parts. However, PHPStorm uses an extended syntax that allows
                // putting the keyword 'static' as first part of the tag to indicate that the property is indeed static.
                if ($staticKeyword !== 'static') {
                    [$type, $variableName, $description] = $this->filterParameterTag($tag, 3);
                }

                if ($type === null || $type === '' || $variableName === null || $variableName === '') {
                    continue;
                }

                $properties[$this->sanitizeText($variableName)] = [
                    'type'        => $this->docblockTypeParser->parse($this->sanitizeText($type)),
                    'isStatic'    => ($staticKeyword === 'static'),
                    'description' => $description !== null ? $this->convertHtmlToMarkdown($description) : null,
                ];
            }
        }

        return [
            $keyName => $properties,
        ];
    }

    /**
     * Filters out information about the magic properties of a class.
     *
     * @param string|null         $docblock
     * @param string              $itemName
     * @param array<string,mixed> $tags
     *
     * phpcs:disable -- since PHPCS thinks these methods are unused.
     *
     * @return array<string,mixed>
     */
    private function filterProperty(?string $docblock, string $itemName, array $tags): array
    {
        // phpcs:enable
        return $this->filterPropertyTag(static::PROPERTY, 'properties', $docblock, $itemName, $tags);
    }

    /**
     * Filters out information about the magic properties of a class.
     *
     * @param string|null         $docblock
     * @param string              $itemName
     * @param array<string,mixed> $tags
     *
     * phpcs:disable -- since PHPCS thinks these methods are unused.
     *
     * @return array<string,mixed>
     */
    private function filterPropertyRead(?string $docblock, string $itemName, array $tags): array
    {
        // phpcs:enable
        return $this->filterPropertyTag(static::PROPERTY_READ, 'propertiesReadOnly', $docblock, $itemName, $tags);
    }

    /**
     * Filters out information about the magic properties of a class.
     *
     * @param string|null         $docblock
     * @param string              $itemName
     * @param array<string,mixed> $tags
     *
     * phpcs:disable -- since PHPCS thinks these methods are unused.
     *
     * @return array<string,mixed>
     */
    private function filterPropertyWrite(?string $docblock, string $itemName, array $tags): array
    {
        // phpcs:enable
        return $this->filterPropertyTag(static::PROPERTY_WRITE, 'propertiesWriteOnly', $docblock, $itemName, $tags);
    }

    /**
     * @param string|null         $docblock
     * @param string              $itemName
     * @param array<string,mixed> $tags
     *
     * phpcs:disable -- since PHPCS thinks these methods are unused.
     *
     * @return array<string,mixed>
     */
    private function filterCategory(?string $docblock, string $itemName, array $tags): array
    {
        // phpcs:enable
        $description = null;

        if (isset($tags[static::CATEGORY])) {
            [$description] = $this->filterParameterTag($tags[static::CATEGORY][0], 1);
        }

        return [
            'category' => $description,
        ];
    }

    /**
     * @param string|null         $docblock
     * @param string              $itemName
     * @param array<string,mixed> $tags
     *
     * phpcs:disable -- since PHPCS thinks these methods are unused.
     *
     * @return array<string,mixed>
     */
    private function filterSubpackage(?string $docblock, string $itemName, array $tags): array
    {
        // phpcs:enable
        $name = null;

        if (isset($tags[static::SUBPACKAGE])) {
            [$name] = $this->filterParameterTag($tags[static::SUBPACKAGE][0], 1);
        }

        return [
            'subpackage' => $name !== '' ? $this->sanitizeText($name) : null,
        ];
    }

    /**
     * @param string|null         $docblock
     * @param string              $itemName
     * @param array<string,mixed> $tags
     *
     * phpcs:disable -- since PHPCS thinks these methods are unused.
     *
     * @return array<string,mixed>
     */
    private function filterLink(?string $docblock, string $itemName, array $tags): array
    {
        // phpcs:enable
        $links = [];

        if (isset($tags[static::LINK])) {
            [$uri, $description] = $this->filterParameterTag($tags[static::LINK][0], 2);

            if ($uri !== null && $uri !== '') {
                $links[] = [
                    'uri'         => $this->sanitizeText($uri),
                    'description' => $description !== null ? $this->convertHtmlToMarkdown($description) : null,
                ];
            }
        }

        return [
            'link' => $links,
        ];
    }

    /**
     * Filters out annotation information.
     *
     * @param string|null         $docblock
     * @param string              $itemName
     * @param array<string,mixed> $tags
     *
     * phpcs:disable -- since PHPCS thinks these methods are unused.
     *
     * @return array<string,mixed>
     */
    private function filterAnnotation(?string $docblock, string $itemName, array $tags): array
    {
        // phpcs:enable
        return [
            'annotation' => isset($tags[static::ANNOTATION]),
        ];
    }

    /**
     * Filters out information about the description.
     *
     * @param string|null         $docblock
     * @param string              $itemName
     * @param array<string,mixed> $tags
     *
     * phpcs:disable -- since PHPCS thinks these methods are unused.
     *
     * @return array<string,mixed>
     */
    private function filterDescription(?string $docblock, string $itemName, array $tags): array
    {
        // phpcs:enable
        $summary = '';
        $description = '';

        $lines = explode("\n", $docblock !== null ? $docblock : '');

        $isReadingSummary = true;

        foreach ($lines as $i => $line) {
            $matches = null;

            if (preg_match(self::TAG_START_REGEX, $line, $matches) === 1 &&
                !$this->docblockAnalyzer->isFullInheritDocSyntax(trim($matches[1]))
            ) {
                break; // Found the start of a tag, the summary and description are finished.
            }

            if ($isReadingSummary && $line === '' && $summary !== '') {
                $isReadingSummary = false;
            } elseif ($isReadingSummary) {
                $summary .= "\n" . trim($line);
            } else {
                $description .= "\n" . $line;
            }
        }

        return [
            'descriptions' => [
                'short' => $this->convertHtmlToMarkdown($this->sanitizeText($summary)),
                'long'  => $this->convertHtmlToMarkdown($this->sanitizeText($description)),
            ],
        ];
    }

    /**
     * @param string $text
     *
     * @return string
     */
    public function convertHtmlToMarkdown(string $text): string
    {
        try {
            return $this->htmlToMarkdownConverter->convert($text);
        } catch (InvalidArgumentException $e) {
            return $text;
        }
    }

    /**
     * @param string $text
     *
     * @return string
     */
    private function sanitizeText(string $text): string
    {
        return trim($text);
    }

    /**
     * Retrieves the specified string with its line separators replaced with the specifed separator.
     *
     * @param string $string
     * @param string $replacement
     *
     * @return string
     */
    private function replaceNewlines(string $string, string $replacement): string
    {
        return str_replace(["\n", "\r\n", "\r", "\n\r", PHP_EOL], $replacement, $string);
    }

    /**
     * Normalizes all types of newlines to the "\n" separator.
     *
     * @param string $string
     *
     * @return string
     */
    private function normalizeNewlines(string $string): string
    {
        return $this->replaceNewlines($string, "\n");
    }
}
