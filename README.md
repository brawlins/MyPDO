# MyPDO

***

## Overview

MyPDO is an extension of PHP's PDO class designed to provide a simplified
workflow and centralized error handling for SQL statements.

There are basic methods for select, insert, update, and delete, as well as a
run method for anything else. The workflow is simplified by combining the
process of preparing statements, executing them, and fetching the results into
a single method call. Depending on the type of query, methods return either a
result set, the number of affected rows, or a boolean representing success or
failure. The debug method handles any exceptions that are raised so you don't
have to write individual error handling routines for each query.

The insert and update methods are designed to easily handle values collected
from a form. You simply provide an associative array of column/value pairs and
it writes the SQL for you. All other methods require a full SQL statement. 

All methods use prepared statements for best security and support both
question mark and named parameter markers. If you don't provide parameter
markers and bindings, the methods will create them for you using the values
given. 

## Methods

###__construct

```
public function __construct($dsn, $user, $password, $options = array()) {}

// example
$MyPDO = new MyPDO('mysql:host=localhost;dbname=database', 'user', 'password');
```

### delete

```
public function delete($sql, $bindings = array()) {}

// delete statements must have a where clause
$result = $MyPDO->delete('DELETE FROM fruits'); // throws exception

// normal
$result = $MyPDO->delete('DELETE FROM fruits WHERE name = "mango"'); 

// with bindings
$result = $MyPDO->delete('DELETE FROM fruits WHERE name = ?', array('mango')); 
$result = $MyPDO->delete('DELETE FROM fruits WHERE name = :fruit', array(':fruit' => 'mango')); 
```

### insert

```
public function insert($table, $values, $bindings = array()) {}

// normal
$values = array('name' => 'banana', 'color' => 'yellow', 'qty' => 5);
$result = $MyPDO->insert('fruits', $values);

// with question mark parameters
$values = array('name' => '?', 'color' => '?', 'qty' => '?');
$bindings = array('apple', 'red', 3);
$result = $MyPDO->insert('fruits', $values, $bindings);

// with named parameters
$values = array('name' => ':name', 'color' => ':color', 'qty' => ':qty');
$bindings = array(':name' => 'pear', ':color' => 'green', ':qty' => 2);
$result = $MyPDO->insert('fruits', $values, $bindings);
```

### run

This method is a catch-all that handles any SQL statement. 

```
public function run($sql, $bindings = array()) {}

// create table
$result = $MyPDO->run('CREATE TABLE fruits (name VARCHAR(20), color VARCHAR(20))');

// alter table
$result = $MyPDO->run('ALTER TABLE fruits ADD COLUMN qty INT(11)');
```

### select

This method returns an array of rows. To select a single value use selectCell().

```
public function select($sql, $bindings = array(), $fetch_style = '', $fetch_argument = '') {}

// normal
$rows = $MyPDO->select('SELECT * FROM fruits');

// with bindings
$rows = $MyPDO->select('SELECT * FROM fruits WHERE qty > ?', array(2));

// one column as one-dimensional array
$rows = $MyPDO->select('SELECT DISTINCT color FROM fruits', NULL, PDO::FETCH_COLUMN);
$rows = $MyPDO->select('SELECT name, qty FROM fruits', NULL, PDO::FETCH_COLUMN, 1); // returns column index 1 (qty)
```

### selectCell

Sometimes you want to retrieve a single value without it being buried in an array. This method returns a scalar value representing the intersection of one row and one column. 

```
public function selectCell($sql, $bindings = array()) {}

// normal
$qty = $MyPDO->selectCell('SELECT qty FROM fruits WHERE fruit = "apple"'); 

// with bindings
$qty = $MyPDO->selectCell('SELECT qty FROM fruits WHERE fruit = ?', array('apple')); 
```

### update

```
public function update($table, $values, $where, $bindings = array()) {}

// normal
$values = array('qty' => 5);
$where = 'name = "bartlett pear"';
$result = $MyPDO->update('fruits', $values, $where);

// mutliple where conditions
$values = array('qty' => 10);
$where = array('name = apple', 'color = green', 'qty < 10');
$result = $MyPDO->update('fruits', $values, $where);

// with bindings
$values = array('name' => ':name');
$where = 'name = :where_name';
$bindings = array(':name' => 'bartlett pear', ':where_name' => 'pear');
$result = $MyPDO->update('fruits', $values, $where, $bindings);
```
