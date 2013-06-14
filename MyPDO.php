<?php
/**
 * PDO wrapper class
 *
 * Provides an extension for PHP's PDO class designed for ease of use and
 * centralized error handling. Adds basic methods for select, insert, update,
 * and delete statements, as well as handling exceptions when SQL errors
 * occur.
 *
 * The insert and update methods are designed to easily handle values
 * collected from a form. You simply provide an associative array of
 * column/value pairs and it writes the SQL for you. All other methods require
 * a full SQL statement.
 * 
 * Inspired by PHP PDO Wrapper Class by imavex.com
 * @see http://www.imavex.com/php-pdo-wrapper-class/
 * 
 * @author Brett Rawlins
 */
class MyPDO extends PDO
{
	/**
	 * SQL statement from the last query
	 * @var string
	 */
	protected $sql;

	/**
	 * PDOStatement object containing the last prepared statement
	 * @var object
	 */
	protected $statement; 

	/**
	 * Bind parameters from the last prepared statement
	 * @var array
	 */	
	protected $bindings;

	/**
	 * Constructor
	 * 
	 * @param string $dsn - the PDO Data Source Name
	 * @param string $user - database user
	 * @param string $password - database password
	 * @param array $options - associative array of connection options
	 */	
	public function __construct($dsn, $user, $password, $options = array())
	{
		// set server environment constants
		require_once 'global/ErrorHandler.php';
		ErrorHandler::defineServerEnv();

		// set default options
		$defaults = array(
			PDO::ATTR_PERSISTENT => true, // persistent connection
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // throw exceptions
			PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES "UTF8"', // character encoding
			PDO::MYSQL_ATTR_FOUND_ROWS => true, // count rows matched for updates even if no changes made
		);

		// create the object
		try {
			parent::__construct($dsn, $user, $password, $defaults);
			// set user options if any
			if ($this && !empty($options) && is_array($options)) {
				foreach ($options as $key => $value) {
					$this->setAttribute($key, $value);
				}
			}
		} catch (PDOException $e) {
			// use self:: because $this doesn't exist - constructor failed
			self::debug($e);
		}
	}

	/**
	 * Display debugging info for PDO Exceptions
	 * 
	 * @param object $e - PDOException object representing the error raised
	 */
	protected function debug($e) 
	{
		// gather error info:
		$error = array();
		$error['Message'] = $e->getMessage();
		// follow backtrace to the top where the error was first raised
		$backtrace = debug_backtrace();
		foreach ($backtrace as $info) {
			if ($info['file'] != __FILE__) {
				$error['Backtrace'] = $info['file'] . ' @ line ' . $info['line'];
			}
		}
		$error['File'] = $e->getFile() . ' @ line ' . $e->getLine();

		// gather SQL params
		if (!empty($this->sql)) {
			$error['SQL statement'] = $this->sql;
		}
		if (!empty($this->bindings)) {
			$error['Bind Parameters'] = '<pre>' . print_r($this->bindings, true) . '</pre>';
		}
		// show args if set
		if (!empty($backtrace[1]['args'])) {
			$error['Args'] = '<pre>' . print_r($backtrace[1]['args'], true) . '</pre>';
		}
		// don't show variables if GLOBALS are set
		if (!empty($context) && empty($context['GLOBALS'])) {
			$error['Current Variables'] = '<pre>' . print_r($context, true) . '</pre>';
		}
		$error['Environment'] = SERVER_ENV_LEVEL . ':' . SERVER_ENV_NAME . ' ' . $_SERVER['SERVER_ADDR'];

		// get css
		$css = '';
		$file = dirname(__FILE__) . '/debug.css';
		if (is_readable($file)) {
			$css = trim(file_get_contents($file));
		}

		// build the message
		$msg = '';
		$msg .= "\n" . '<style type="text/css">' . "\n" . $css . "\n" . '</style>';
		$msg .= "\n" . '<div class="debug">' . "\n\t" . '<h3>' . __METHOD__ . '</h3>';
		foreach ($error as $key => $value) {
			$msg .= "\n\t" . '<label>' . $key . ':</label>' . $value;
		}
		$msg .= "\n" . '</div>';

		// customize error handling based on environment:
		if (SERVER_ENV_NAME == 'PROD') { 
			// do nothing
		} else { 
			echo $msg;
		}

		// don't execute default PHP error handler
		return true;
	}

	/**
	 * Return a prepared statement
	 *
	 * Extends PDO::prepare to add basic error handling
	 * 
	 * @param  string $sql - SQL statement to prepare
	 * @param  array $options - array of key/value pairs to set attributes for the PDOStatement object (@see PDO::prepare)
	 * @return mixed - PDOStatement object or false on failure
	 */
	public function prepare($sql, $options = array())
	{
		// cleanup
		$this->sql = trim($sql);

		try {
			// prepare the statement
			$this->statement = NULL;
			if ($this->statement = parent::prepare($this->sql, $options)) {
				return $this->statement;
			}
		} catch (PDOException $e) {
			$this->debug($e);
			return false;
		}
	}

	/**
	 * Bind parameters and execute a prepared statement
	 * 
	 * @param  array $bindings - array of values to be substituted for the parameter markers
	 * @return bool 
	 */
	public function execute($bindings)
	{
		// cleanup
		$this->bindings = (empty($bindings)) ? NULL : $bindings; 

		if (!empty($this->statement)) {
			try {
				return $this->statement->execute($bindings);
			} catch (PDOException $e) {
				$this->debug($e);
				return false;
			}
		}
	}

	/**
	 * Return the results of the given SELECT statement
	 *
	 * Accomodates any select statement that returns an array. To select a
	 * single row and column (scalar value) use selectCell().
	 *
	 * @param  string $sql - SQL statement
	 * @param  array $bindings - array of values to be substituted for the parameter markers
	 * @param  int $fetch_style - PDO::FETCH_* constant that controls the contents of the returned array (@see PDOStatement::fetch())
	 * @param  mixed $fetch_argument - column index, class name, or other argument depending on the value of the $fetch_style parameter
	 * @return array - array of results or false on failure
	 */
	public function select($sql, $bindings = array(), $fetch_style = '', $fetch_argument = '')
	{
		// prepare the statement
		if ($this->prepare($sql)) {
			// bind and execute
			if ($this->execute($bindings)) {
				// set default fetch mode
				$fetch_style = (empty($fetch_style)) ? PDO::FETCH_ASSOC : $fetch_style; 
				// return the results
				if (!empty($fetch_argument)) {
					return $this->statement->fetchAll($fetch_style, $fetch_argument);
				}
				return $this->statement->fetchAll($fetch_style);
			}
			return false;
		}
		return false;
	}

	/**
	 * Return the value of a single cell (row & column) for the given SELECT statement
	 * 
	 * @param  string $sql - SQL statement
	 * @param  array $bindings - array of values to be substituted for the parameter markers
	 * @return mixed - the value or false on failure
	 */
	public function selectCell($sql, $bindings = array())
	{
		// prepare the statement
		if ($this->prepare($sql)) {
			// bind and execute
			if ($this->execute($bindings)) {
				// return the value
				return $this->statement->fetch(PDO::FETCH_COLUMN);
			}
			return false;
		}
		return false;
	}

	/**
	 * Run the given SQL statement and return the result
	 * 
	 * @param  string $sql - SQL statement
	 * @param  array $bindings - array of values to be substituted for the parameter markers
	 * @return mixed - the value or false on failure
	 */
	public function run($sql, $bindings = array())
	{
		// prepare the statement
		if ($this->prepare($sql)) {
			// require a WHERE clause for deletes
			try {
				if (preg_match('/delete/i', $this->sql) && !preg_match('/where/i', $this->sql)) {
					throw new PDOException('Missing WHERE clause for DELETE statement');
				}
			} catch (PDOException $e) {
				$this->debug($e);
				return false;
			}
			// prevent unsupported actions
			try {
				if (!preg_match('/(select|describe|delete|insert|update|create|alter)+/i', $this->sql)) {
					throw new PDOException('Unsupported SQL command');
				}
			} catch (PDOException $e) {
				$this->debug($e);
				return false;
			}

			// bind and execute
			if ($success = $this->execute($bindings)) {
				// return the result
				if (preg_match('/(delete|insert|update)/i', $this->sql)) {
					return $this->statement->rowCount();
				} else if (preg_match('/(select|describe)/i', $this->sql)) {
					return $this->statement->fetchAll(PDO::FETCH_ASSOC);
				} else if (preg_match('/(create|alter)/i', $this->sql)) {
					return $success;
				}
			}
			return false;
		}
		return false;
	}

	/**
	 * Run the given DELETE statement and return the number of affected rows
	 * 
	 * @param  string $sql - SQL statement
	 * @param  array $bindings - array of values to be substituted for the parameter markers
	 * @return int - number of affected rows or false on failure
	 */
	public function delete($sql, $bindings = array())
	{
		return $this->run($sql, $bindings);
	}

    /**
     * Filter out any array values that don't match a column in the table
     *
     * @param array $values - associative array of values
     * @param string $table - table name
     * @return array - the filtered array
     */
    public function filter($values, $table)
    {
        // get columns in the table
        try {
        	$this->sql = 'SHOW COLUMNS FROM ' . $table; 
        	$sth = $this->query($this->sql);
        	$info = $sth->fetchAll();
        } catch (PDOException $e) {
    		$this->debug($e);
    		return false;
        }

        // compile the column names
        $ai_fields = array(); // auto-increment fields
        $columns = array();
        foreach ($info as $item) {
            $columns[] = $item['Field'];
            // identify auto-increment fields
            if (isset($item['Extra']) && $item['Extra'] == 'auto_increment') {
            	$ai_fields[] = $item['Field'];
            }
        }

        // remove items that don't match a column
        foreach ($values as $name => $value) {
            if (!in_array($name, $columns)) {
                unset($values[$name]);
            }
        }

        // remove auto-increment fields
        if (!empty($ai_fields)) {
        	foreach ($ai_fields as $item) {
	        	unset($values[$item]);
        	}
        }

        return $values;
    }

    /**
     * Run the given INSERT statement and return the number of affected rows
     * 
	 * If no bindings are given, we create them from the values. We
	 * want to let PDO bind parameter values because it automatically
	 * handles quoting and NULL values properly.
     * 
     * @param string $table - table name
     * @param array $values - associative array of column/value pairs
     * @param array $bindings - array of values to be substituted for the parameter markers 
     * @return int - number of affected rows or false on failure
     */
	public function insert($table, $values, $bindings = array())
	{
		// filter values for table
		$values = $this->filter($values, $table);

		// Build the SQL:
        $sql = 'INSERT INTO '.$table.' (';
        // add column names
        $i = 0;
        foreach ($values as $column => $value) {
            $sql .= ($i == 0) ? $column : ', ' . $column;
            $i++;
        }
        $sql .= ') VALUES (';
        // add values
        $i = 0;
        if (empty($bindings)) {
        	$bindings = array_values($values);
	        foreach ($values as $value) {
	        	$sql .= ($i == 0) ? '?' : ', ?'; 
	            $i++;
	        }
        } else {
	        foreach ($values as $value) {
	        	$sql .= ($i == 0) ? $value : ', '.$value; 
	            $i++;
	        }
        }
        $sql .= ')';

		// run the query
		return $this->run($sql, $bindings);
	}

	/**
	 * Updates the table with the given values and returns the number of affected rows
	 *
	 * Designed for easily updating a record using values collected from a
	 * form. If no bindings are given, they will be created using the given
	 * values so we can take advantage of the benefits of prepared statements.
	 * 
	 * N.B. Does not support where clauses that use the "IN" keyword.
	 * 
	 * @param  string $table - table name
	 * @param  array  $values - associative array of column/value pairs
	 * @param  array $where - where clause as an array of conditions (string will be converted to array)
	 * @param  array $bindings - array of values to be substituted for the parameter markers in $values and/or $where
	 * @return int - number of affected rows or false on failure
	 */
	public function update($table, $values, $where, $bindings = array())
	{
		// filter values for table
		$values = $this->filter($values, $table);

		// Build the SQL:
        $sql = 'UPDATE '.$table.' SET ';

        // add columns and parameter markers
        $final_bindings = array();
        $i = 0;
        foreach ($values as $column => $value) {
        	$marker = $bound_value = NULL;
        	// get the binding
        	if (preg_match('/(:\w+|\?)/', $value, $matches)) {
        		if (strpos(':', $matches[1]) !== false) {
        			// look up the value (named parameters can be in any order)
        			$marker = $matches[1];
        			$bound_value = $bindings[$matches[1]]; 
        		} else {
        			// get the next value (question mark parameters are given in order)
        			$marker = ':'.$column;
        			$bound_value = array_shift($bindings);
        		}
    		// create the binding
        	} else {
    			$marker = ':'.$column;
    			$bound_value = $value;
        	}
        	// add the binding
        	$final_bindings[$marker] = $bound_value;

        	// add the SQL
            $sql .= ($i == 0) ? $column.' = '.$marker : ', '.$column.' = '.$marker;
            $i++;
        } 

        // handle the where clause and bindings
		if (!empty($where)) {

			// convert where string to array
			if (!is_array($where)) {
				$where = preg_split('/\b(where|and)\b/i', $where, NULL, PREG_SPLIT_NO_EMPTY);
				$where = array_map('trim', $where);
			}

			// loop through each condition
        	foreach ($where as $i => $condition) {
	        	$marker = $bound_value = NULL;
        		// split up condition into parts (column, operator, value)
        		preg_match('/(\w+)\s*(=|<|>|!)+\s*(.+)/i', $condition, $parts);
        		if (!empty($parts)) {
        			// assign parts to variables
	        		list( , $column, $operator, $value) = $parts; 
		        	// get the binding
		        	if (preg_match('/(:\w+|\?)/', $value, $matches)) {
		        		if (strpos(':', $matches[1]) !== false) {
		        			// look up the value (named parameters can be in any order)
		        			$marker = $matches[1];
		        			$bound_value = $bindings[$matches[1]];
		        		} else {
		        			// get the next value (question mark parameters are given in order)
		        			$marker = ':where_'.$column;
		        			$bound_value = array_shift($bindings);
		        		}
	        		// create the binding
		        	} else {
		    			$marker = ':where_'.$column;
		    			$bound_value = $value;
		        	}
		        	// add the binding
		        	$final_bindings[$marker] = $bound_value;
		        	// update the condition (replace value with marker)
		        	$where[$i] = substr_replace($condition, $marker, strpos($condition, $value));
        		}
        	}

	        // add the where clause
			foreach ($where as $i => $condition) {
	            $sql .= ($i == 0) ? ' WHERE '.$condition : ' AND '.$condition;
			}
		}

        // run the query
        return $this->run($sql, $final_bindings);
	}	

}