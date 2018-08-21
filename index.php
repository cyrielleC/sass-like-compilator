<?php


class Element
{

    const OPENING_BRACKET = '{';
    const CLOSING_BRACKET = '}';
    const EOL = ';';

    /**
     * @var string
     */
    private $selector;

    /**
     * @var array
     */
    private $properties;

    /**
     * @var Element[]
     */
    private $subElements;

    /**
     * Element constructor.
     * @param string $selector
     * @param array $properties
     * @param array $subElements
     */
    public function __construct($selector = '', $properties = [], $subElements = [])
    {
        $this->selector = $selector;
        $this->properties = $properties;
        $this->subElements = $subElements;
    }

    /**
     * @param $property
     */
    public function addLine($property)
    {
        $this->properties[] = $property;
    }

    /**
     * @param Element $element
     */
    public function addSubElement(Element $element)
    {
        $this->subElements[] = $element;
    }

    /**
     * @param string $endOfLine
     * @param string $upperSelector
     * @return string
     */
    public function toString($endOfLine = '', $upperSelector = '')
    {
        $string = '';
        $fullSelector = $upperSelector.' '.$this->selector;
        // Print itself if it has own properties
        if (!empty($this->properties)) {
            $string .= $fullSelector.self::OPENING_BRACKET.$endOfLine;
            foreach ($this->properties as $line) {
                $string .= $line.self::EOL.$endOfLine;
            }
            $string .= self::CLOSING_BRACKET.$endOfLine;
        }
        // Print sub element then
        foreach ($this->subElements as $element) {
            $string .= $element->toString($endOfLine, $fullSelector);
        }

        return $string;
    }
}

class SASSLikeCompilator
{

    /**
     * @param $cssString
     * @param string $selector
     * @return Element
     * @throws Exception
     */
    private static function compileElement(&$cssString, $selector = '')
    {
        $currentElement = new Element($selector);

        // The String will be consumed incrementally until it's over
        while (strlen($cssString) != 0 && $newCSSChunk = strpbrk(
                $cssString,
                Element::OPENING_BRACKET.Element::CLOSING_BRACKET.Element::EOL
            )) {
            // The current "operator"
            $separator = $newCSSChunk[0];
            // The chunk before, either a selector if we're dealing with an opening bracket, or a property if we're dealing with a ";"
            $before = trim(strstr($cssString, $newCSSChunk[0], true));
            // The new chunk stripped from the current operator and trimmed
            $cssString = trim(substr($newCSSChunk, 1));

            // If it's a closing bracket,
            // then return the element we've reached the end of the chunk regarding the element
            if ($separator == Element::CLOSING_BRACKET) {
                // Except if the before is not empty as it should be
                if ($before) {
                    throw new Exception('Missing end of line');
                }
                /*
                 * If element has ended prematurely :
                 * - only the ROOT with no selector has to end without a closing bracket
                 *      => this covers the case when there is a closing bracket which has not been opened
                 * - NON ROOT elements should end with a closing bracket
                 *      => this covers both cases when we end with too much OR not enough closing bracket at the very end
                 */
                if (!$selector && $separator == Element::CLOSING_BRACKET || $selector && $separator != Element::CLOSING_BRACKET) {
                    // We're out of the loop too soon, means something went wrong with the opening and closing brackets
                    throw new Exception("Unexpected end of element");
                }
                break;
            }

            // If it's a ";" it means it's a property, add it to the element and move on
            if ($separator == Element::EOL) {
                // If we're at the root, there shouldn't be any property;
                if (!$selector) {
                    throw new Exception('Unexpected property at root level');
                }
                $currentElement->addLine($before);
                continue;
            }
            // Otherwise, it's a new sub element, add it recursively
            // If selector is empty syntax issue
            if (!$before) {
                throw new Exception('Missing selector');
            }
            $currentElement->addSubElement(
                self::compileElement($cssString, $before)
            );
        }

        return $currentElement;
    }

    /**
     * This alias is used in order for the first call not be able to give a selector (it should be a root css string!)
     * @param $cssString
     * @return Element
     * @throws Exception
     */
    public static function compileCSSSheet($cssString)
    {
        return self::compileElement($cssString);
    }
}


$css = ".my-flowers {
  width: 30px;
  height: 30px;
  border-radius: 15px;

  .are {
    .beautiful {
        color: #00f;
    }

    .ugly {
        color: #f00;
        width: 15px;
    }
  }
  
 
}

.my-tailor {
    height: 120px;

    .is-rich {
        content: '$';
    }

    .is-not-rich {
        content: '-1';
    }
    
    width: 120px;

}";

$test = SASSLikeCompilator::compileCSSSheet($css);
echo $test->toString();
