<?php

namespace PhpXmlRpc;

use PhpXmlRpc\Helper\Charset;

class Value implements \Countable, \IteratorAggregate
{
    public static $xmlrpcI4 = "i4";
    public static $xmlrpcInt = "int";
    public static $xmlrpcBoolean = "boolean";
    public static $xmlrpcDouble = "double";
    public static $xmlrpcString = "string";
    public static $xmlrpcDateTime = "dateTime.iso8601";
    public static $xmlrpcBase64 = "base64";
    public static $xmlrpcArray = "array";
    public static $xmlrpcStruct = "struct";
    public static $xmlrpcValue = "undefined";
    public static $xmlrpcNull = "null";

    public static $xmlrpcTypes = array(
        "i4" => 1,
        "int" => 1,
        "boolean" => 1,
        "double" => 1,
        "string" => 1,
        "dateTime.iso8601" => 1,
        "base64" => 1,
        "array" => 2,
        "struct" => 3,
        "null" => 1,
    );

    /// @todo: do these need to be public?
    public $me = array();
    public $mytype = 0;
    public $_php_class = null;

    /**
     * Build an xmlrpc value.
     * When no value or type is passed in, the value is left uninitialized, and the value can be added later
     *
     * @param mixed $val
     * @param string $type any valid xmlrpc type name (lowercase). If null, 'string' is assumed
     */
    public function __construct($val = -1, $type = '')
    {
        // optimization creep - do not call addXX, do it all inline.
        // downside: booleans will not be coerced anymore
        if ($val !== -1 || $type != '') {
            switch ($type) {
                case '':
                    $this->mytype = 1;
                    $this->me['string'] = $val;
                    break;
                case 'i4':
                case 'int':
                case 'double':
                case 'string':
                case 'boolean':
                case 'dateTime.iso8601':
                case 'base64':
                case 'null':
                    $this->mytype = 1;
                    $this->me[$type] = $val;
                    break;
                case 'array':
                    $this->mytype = 2;
                    $this->me['array'] = $val;
                    break;
                case 'struct':
                    $this->mytype = 3;
                    $this->me['struct'] = $val;
                    break;
                default:
                    error_log("XML-RPC: " . __METHOD__ . ": not a known type ($type)");
            }
        }
    }

    /**
     * Add a single php value to an (uninitialized) xmlrpc value.
     *
     * @param mixed $val
     * @param string $type
     *
     * @return int 1 or 0 on failure
     */
    public function addScalar($val, $type = 'string')
    {
        $typeOf = null;
        if (isset(static::$xmlrpcTypes[$type])) {
            $typeOf = static::$xmlrpcTypes[$type];
        }

        if ($typeOf !== 1) {
            error_log("XML-RPC: " . __METHOD__ . ": not a scalar type ($type)");
            return 0;
        }

        // coerce booleans into correct values
        // NB: we should either do it for datetimes, integers and doubles, too,
        // or just plain remove this check, implemented on booleans only...
        if ($type == static::$xmlrpcBoolean) {
            if (strcasecmp($val, 'true') == 0 || $val == 1 || ($val == true && strcasecmp($val, 'false'))) {
                $val = true;
            } else {
                $val = false;
            }
        }

        switch ($this->mytype) {
            case 1:
                error_log('XML-RPC: ' . __METHOD__ . ': scalar xmlrpc value can have only one value');
                return 0;
            case 3:
                error_log('XML-RPC: ' . __METHOD__ . ': cannot add anonymous scalar to struct xmlrpc value');
                return 0;
            case 2:
                // we're adding a scalar value to an array here
                $this->me['array'][] = new Value($val, $type);

                return 1;
            default:
                // a scalar, so set the value and remember we're scalar
                $this->me[$type] = $val;
                $this->mytype = $typeOf;

                return 1;
        }
    }

    /**
     * Add an array of xmlrpc values objects to an xmlrpc value.
     *
     * @param Value[] $values
     *
     * @return int 1 or 0 on failure
     *
     * @todo add some checking for $values to be an array of xmlrpc values?
     */
    public function addArray($values)
    {
        if ($this->mytype == 0) {
            $this->mytype = static::$xmlrpcTypes['array'];
            $this->me['array'] = $values;

            return 1;
        } elseif ($this->mytype == 2) {
            // we're adding to an array here
            $this->me['array'] = array_merge($this->me['array'], $values);

            return 1;
        } else {
            error_log('XML-RPC: ' . __METHOD__ . ': already initialized as a [' . $this->kindOf() . ']');
            return 0;
        }
    }

    /**
     * Add an array of named xmlrpc value objects to an xmlrpc value.
     *
     * @param Value[] $values
     *
     * @return int 1 or 0 on failure
     *
     * @todo add some checking for $values to be an array?
     */
    public function addStruct($values)
    {
        if ($this->mytype == 0) {
            $this->mytype = static::$xmlrpcTypes['struct'];
            $this->me['struct'] = $values;

            return 1;
        } elseif ($this->mytype == 3) {
            // we're adding to a struct here
            $this->me['struct'] = array_merge($this->me['struct'], $values);

            return 1;
        } else {
            error_log('XML-RPC: ' . __METHOD__ . ': already initialized as a [' . $this->kindOf() . ']');
            return 0;
        }
    }

    /**
     * Returns a string containing "struct", "array", "scalar" or "undef" describing the base type of the value.
     *
     * @return string
     */
    public function kindOf()
    {
        switch ($this->mytype) {
            case 3:
                return 'struct';
                break;
            case 2:
                return 'array';
                break;
            case 1:
                return 'scalar';
                break;
            default:
                return 'undef';
        }
    }

    protected function serializedata($typ, $val, $charsetEncoding = '')
    {
        $rs = '';

        if (!isset(static::$xmlrpcTypes[$typ])) {
            return $rs;
        }

        switch (static::$xmlrpcTypes[$typ]) {
            case 1:
                switch ($typ) {
                    case static::$xmlrpcBase64:
                        $rs .= "<${typ}>" . base64_encode($val) . "</${typ}>";
                        break;
                    case static::$xmlrpcBoolean:
                        $rs .= "<${typ}>" . ($val ? '1' : '0') . "</${typ}>";
                        break;
                    case static::$xmlrpcString:
                        // G. Giunta 2005/2/13: do NOT use htmlentities, since
                        // it will produce named html entities, which are invalid xml
                        $rs .= "<${typ}>" . Charset::instance()->encodeEntities($val, PhpXmlRpc::$xmlrpc_internalencoding, $charsetEncoding) . "</${typ}>";
                        break;
                    case static::$xmlrpcInt:
                    case static::$xmlrpcI4:
                        $rs .= "<${typ}>" . (int)$val . "</${typ}>";
                        break;
                    case static::$xmlrpcDouble:
                        // avoid using standard conversion of float to string because it is locale-dependent,
                        // and also because the xmlrpc spec forbids exponential notation.
                        // sprintf('%F') could be most likely ok but it fails eg. on 2e-14.
                        // The code below tries its best at keeping max precision while avoiding exp notation,
                        // but there is of course no limit in the number of decimal places to be used...
                        $rs .= "<${typ}>" . preg_replace('/\\.?0+$/', '', number_format((double)$val, 128, '.', '')) . "</${typ}>";
                        break;
                    case static::$xmlrpcDateTime:
                        if (is_string($val)) {
                            $rs .= "<${typ}>${val}</${typ}>";
                        } elseif (is_a($val, 'DateTime')) {
                            $rs .= "<${typ}>" . $val->format('Ymd\TH:i:s') . "</${typ}>";
                        } elseif (is_int($val)) {
                            $rs .= "<${typ}>" . strftime("%Y%m%dT%H:%M:%S", $val) . "</${typ}>";
                        } else {
                            // not really a good idea here: but what shall we output anyway? left for backward compat...
                            $rs .= "<${typ}>${val}</${typ}>";
                        }
                        break;
                    case static::$xmlrpcNull:
                        if (PhpXmlRpc::$xmlrpc_null_apache_encoding) {
                            $rs .= "<ex:nil/>";
                        } else {
                            $rs .= "<nil/>";
                        }
                        break;
                    default:
                        // no standard type value should arrive here, but provide a possibility
                        // for xmlrpc values of unknown type...
                        $rs .= "<${typ}>${val}</${typ}>";
                }
                break;
            case 3:
                // struct
                if ($this->_php_class) {
                    $rs .= '<struct php_class="' . $this->_php_class . "\">\n";
                } else {
                    $rs .= "<struct>\n";
                }
                $charsetEncoder = Charset::instance();
                foreach ($val as $key2 => $val2) {
                    $rs .= '<member><name>' . $charsetEncoder->encodeEntities($key2, PhpXmlRpc::$xmlrpc_internalencoding, $charsetEncoding) . "</name>\n";
                    //$rs.=$this->serializeval($val2);
                    $rs .= $val2->serialize($charsetEncoding);
                    $rs .= "</member>\n";
                }
                $rs .= '</struct>';
                break;
            case 2:
                // array
                $rs .= "<array>\n<data>\n";
                foreach ($val as $element) {
                    //$rs.=$this->serializeval($val[$i]);
                    $rs .= $element->serialize($charsetEncoding);
                }
                $rs .= "</data>\n</array>";
                break;
            default:
                break;
        }

        return $rs;
    }

    /**
     * Returns xml representation of the value. XML prologue not included.
     *
     * @param string $charsetEncoding the charset to be used for serialization. if null, US-ASCII is assumed
     *
     * @return string
     */
    public function serialize($charsetEncoding = '')
    {
        // add check? slower, but helps to avoid recursion in serializing broken xmlrpc values...
        //if (is_object($o) && (get_class($o) == 'xmlrpcval' || is_subclass_of($o, 'xmlrpcval')))
        //{
        reset($this->me);
        list($typ, $val) = each($this->me);

        return '<value>' . $this->serializedata($typ, $val, $charsetEncoding) . "</value>\n";
        //}
    }

    /**
     * Checks whether a struct member with a given name is present.
     * Works only on xmlrpc values of type struct.
     *
     * @param string $key the name of the struct member to be looked up
     *
     * @return boolean
     */
    public function structmemexists($key)
    {
        return array_key_exists($key, $this->me['struct']);
    }

    /**
     * Returns the value of a given struct member (an xmlrpc value object in itself).
     * Will raise a php warning if struct member of given name does not exist.
     *
     * @param string $key the name of the struct member to be looked up
     *
     * @return Value
     */
    public function structmem($key)
    {
        return $this->me['struct'][$key];
    }

    /**
     * Reset internal pointer for xmlrpc values of type struct.
     */
    public function structreset()
    {
        reset($this->me['struct']);
    }

    /**
     * Return next member element for xmlrpc values of type struct.
     *
     * @return Value
     */
    public function structeach()
    {
        return each($this->me['struct']);
    }

    /**
     * Returns the value of a scalar xmlrpc value.
     *
     * @return mixed
     */
    public function scalarval()
    {
        reset($this->me);
        list(, $b) = each($this->me);

        return $b;
    }

    /**
     * Returns the type of the xmlrpc value.
     * For integers, 'int' is always returned in place of 'i4'.
     *
     * @return string
     */
    public function scalartyp()
    {
        reset($this->me);
        list($a,) = each($this->me);
        if ($a == static::$xmlrpcI4) {
            $a = static::$xmlrpcInt;
        }

        return $a;
    }

    /**
     * Returns the m-th member of an xmlrpc value of struct type.
     *
     * @param integer $key the index of the value to be retrieved (zero based)
     *
     * @return Value
     */
    public function arraymem($key)
    {
        return $this->me['array'][$key];
    }

    /**
     * Returns the number of members in an xmlrpc value of array type.
     *
     * @return integer
     *
     * @deprecated use count() instead
     */
    public function arraysize()
    {
        return count($this->me['array']);
    }

    /**
     * Returns the number of members in an xmlrpc value of struct type.
     *
     * @return integer
     *
     * @deprecateduse count() instead
     */
    public function structsize()
    {
        return count($this->me['struct']);
    }

    /**
     * Returns the number of members in an xmlrpc value:
     * - 0 for uninitialized values
     * - 1 for scalars
     * - the number of elements for structs and arrays
     *
     * @return integer
     */
    public function count()
    {
        switch ($this->mytype) {
            case 3:
                count($this->me['struct']);
            case 2:
                return count($this->me['array']);
            case 1:
                return 1;
            default:
                return 0;
        }
    }

    /**
     * Implements the IteratorAggregate interface
     *
     * @return ArrayIterator
     */
    public function getIterator() {
        return new \ArrayIterator($this->me);
    }
}