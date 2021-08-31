<?php

require_once('/var/www/euras/EIS/libs/db.php');

/**
 * DB management interface.
 *
 * Have all the functions to manage one table with cache.
 * For multiple table queries, create new functions on child classes (without cache). 
 * 
 * Custom ASWO AF400
 *
 * @author	Khalaghi camille
 */
class Dao {
	/** Nom de l'objet de critère. */
	// protected $_criteriaObject = '\Temma\DaoCriteria';
	/** Connexion au serveur de cache. */
	protected $_cache = null;
	/** Indique s'il faut désactiver le cache. */
	protected $_disableCache = false;
	/** Nom de la base de données. */
	protected $_dbName = null;
	/** Nom de la table. */
	protected $_tableName = null;
	/** Nom de la clé primaire. */
	protected $_idField = null;
	/** Table de mapping des champs de la table. */
	protected $_fields = null;
	/** Liste des champs après génération. */
	private $_fieldsString = null;

	/**
	 * Constructeur.
	 * @param	FineCache	$cache		(optionnel) Connexion au serveur de cache.
	 * @param	string		$tableName	(optionnel) Nom de la table concernée.
	 * @param	string		$idField	(optionnel) Nom de la clé primaire. "id" par défaut.
	 * @param	string		$dbName		(optionnel) Nom de la base de données contenant la table.
	 * @param	array		$fields		(optionnel) Hash de mapping des champs de la table ("champ dans la table" => "nom aliasé").
	 * @param	string		$criteriaObject	(optionnel) Nom de l'objet de critères. "\Temma\DaoCriteria" par défaut.
	 * @throws	\Temma\Exceptions\DaoException	Si l'objet de critère n'est pas bon.
	 */
	public function __construct(\FineCache $cache=null, $tableName=null, $idField='id', $dbName=null, $fields=null, $criteriaObject=null) {
		/*
		$this->_cache = $cache;
		if (empty($this->_tableName))
			$this->_tableName = $tableName;
		if (empty($this->_idField))
			$this->_idField = $idField;
		if (empty($this->_dbName))
			$this->_dbName = $dbName;
		if (is_array($fields))
			$this->_fields = $fields;
		if (is_null($this->_fields))
			$this->_fields = array();
		if (!empty($criteriaObject)) {
			if (!is_subclass_of($criteriaObject, '\Temma\DaoCriteria'))
				throw new \Temma\Exceptions\DaoException("Bad object type.", \Temma\Exceptions\DaoException::CRITERIA);
			$this->_criteriaObject = $criteriaObject;
		}
		*/
	}
	/**
	 * Récupère un enregistrement a partir d'une requete select.
	 * @param	string	$sql	Requete
	 * @return	array	Hash de données.
	 */
	public function queryOne($sql) {
		$q1 = query_db2($sql, true);
		$res = result_db2($q1);
		
		unset($q1);
		return ($res);
	}
	
	/**
	 * Execute une requete sql.
	 * @param	string	$sql	Requete
	 */
	public function execute($sql) {
		query_db2($sql, true);
	}

	/**
	 * Récupère un tableau d'enregistrements a partir d'une requete select.
	 * @param	string	$sql	Requete
	 * @param	string	$db		(optionnel) Type de base de donnee a utiliser ('db1', 'db2'). default : 'db2' .
	 * @return	array	Hash de données.
	 */
	public function queryAll($sql, $dbType='db2') {
		// initialisations
		{
			if ($dbType=='db2') {
				$queryDb = 'query_db2';
				$resultDb = 'result_db2';
			} else /* if ($dbType='db1') */ {
				$queryDb = 'query_db1';
				$resultDb = 'result_db1';
			}
		}
		// lanch request
		$res = array();
		$q1 = $queryDb($sql,true);
		
		// get results
		while ($r = $resultDb($q1)) {
			$res[] = $r; 
			unset($r);
		}
		
		// return
		unset($r, $q1, $queryDb, $resultDb);
		return ($res);
	}
	/**
	 * Génère un objet gestion des critères de requête.
	 * @param	string	$type	(optionnel) Type de combinaison des critères ("and", "or"). "and" par défaut.
	 * @return 	TemmaDaoCriteria	L'objet de critère.
	 */
	 /*
	public function criteria($type='and') {
		return (new $this->_criteriaObject($this->_db, $this, $type));
	}
	*/
	/**
	 * Retourne le nombre d'enregistrements dans la table.
	 * @param	TemmaDaoCriteria	$criteria	(optionnel) Critères de recherche.
	 * @return	int	Le nombre.
	 */
	public function count($criteria=null) {
		$cacheVarName = '__dao:' . $this->_dbName . ':' . $this->_tableName . ':count';
		$sql = 'SELECT COUNT(*) AS nb
			FROM ' . (empty($this->_dbName) ? '' : ($this->_dbName . '.')) . "`" . $this->_tableName . "`";
		if (isset($criteria)) {
			$where = $criteria->generate();
			if (!empty($where)) {
				$sql .= ' WHERE ' . $where;
				$cacheVarName .= ':' . hash('md5', $sql);
			}
		}
		// on cherche la donnée en cache
		if (($nb = $this->_getCache($cacheVarName)) !== null)
			return ($nb);
		// exécution de la requête
		$data = $this->queryOne($sql);
		// écriture de la donnée en cache
		$this->_setCache($cacheVarName, $data['nb']);
		return ($data['nb']);
	}
	
	/**
	 * Récupère un enregistrement dans la base de données, à partir de son identifiant.
	 * @param	int|string	$id	Identifiant de l'enregistrement à récupérer.
	 * @return	array	Hash de données.
	 */
	public function get($id) {
		// verif
		if (empty($id))
			return (array());
		// on cherche la donnée en cache
		$cacheVarName = '__dao:' . $this->_dbName . ':' . $this->_tableName . ":get:$id";
		if (($data = $this->_getCache($cacheVarName)) !== null)
			return ($data);
		// exécution de la requête
		$sql = 'SELECT ' . $this->_getFieldsString() . ' FROM ' .
			$this->getTableName() . 
			' WHERE ' . $this->_idField . " = " . $this->quote($id) . "  fetch first row only";
		$data = $this->queryOne($sql);
		// écriture de la donnée en cache
		$this->_setCache($cacheVarName, $data);
		
		// retour
		unset($sql, $cacheVarName);
		return ($data);
	}
	
	/**
	 * Récupère des enregistrements dans la base de données, à partir d'une serie d'identifiant.
	 * @param	array	$ids	Identifiants des enregistrements à récupérer.
	 * @return	array|null	Liste d'enregistrements. null si rien trouve.
	 */
	public function getFromIds($ids) {
		// on cherche la donnée en cache
		{
			$cacheVarName = '__dao:' . $this->_dbName . ':' . $this->_tableName . ":getmultiple:" . implode(':', $ids);
			if (($data = $this->_getCache($cacheVarName)) !== null)
				return ($data);
		}
		// encodage des ids
		{
			$encodedIds = array();
			foreach ($ids as $id)
				$encodedIds[] = $this->quote($id);
		}

		// exécution de la requête
		{
			$sql = 'SELECT ' . $this->_getFieldsString() . ' FROM ' . $this->getTableName() . 
				' WHERE ' . $this->_idField . " IN (" . implode(", " , $encodedIds) . ") ";
			$data = $this->queryAll($sql);
			if (empty($data)) {
				unset($cacheVarName, $data, $encodedIds, $id, $sql);
				return (null);
			}
		}
		
		// indexation
		{
			$res = array();
			foreach($data as $line)
				$res[ trim($line[$this->_idField]) ] = $line;
		}

		// écriture de la donnée en cache
		$this->_setCache($cacheVarName, $res);

		// retour
		unset($encodedIds, $sql, $cacheVarName, $data, $encodedIds, $id);
		return ($res);
	}
	
	/**
	 * Récupère tous les enregistrements de la table.
	 * Attention au volume de donnees !
	 * 
	 * @param	array	$sort	(optionnel)	Sort fields. ex : ["order" => "ASC"]
	 *
	 * @return	array|null	Liste d'enregistrements. null si rien trouve.
	 */
	public function getAll($sort=null) {
		// on cherche la donnée en cache
		{
			$cacheVarName = '__dao:' . $this->_dbName . ':' . $this->_tableName . ':getall';
			if (($data = $this->_getCache($cacheVarName)) !== null)
				return ($data);
		}

		// exécution de la requête
		{
			// sort
			{
				$mySort = '';
				if (!is_null($sort)) {
					$sortList = array();
					if (is_string($sort))
						$sortList[] = "$sort";
					else if ($sort == RAND())
						$sortList[] = RAND();
					else if (is_array($sort)) {
						foreach ($sort as $key => $value) {
							$field = is_int($key) ? $value : $key;
							if (is_array($this->_fields) && ($field2 = array_search($field, $this->_fields)) !== false && !is_int($field2))
								$field = $field2;
							$sortType = (!is_int($key) && (!strcasecmp($value, 'asc') || !strcasecmp($value, 'desc'))) ? $value : 'ASC';
							$sortList[] = "$field $sortType";
						}
					}
					if (!empty($sortList))
						$mySort = ' ORDER BY ' . implode(', ', $sortList) . ' ';
					unset($sortList, $field, $field2, $sortType);
				}
			}

			$sql = 'SELECT ' . $this->_getFieldsString() . ' FROM ' . $this->getTableName() . ' ' . $mySort . ' ';
			$data = $this->queryAll($sql);
			if (empty($data)) {
				unset($cacheVarName, $data, $encodedIds, $id, $sql, $mySort);
				return (null);
			}
		}
		
		// indexation
		{
			$res = array();
			foreach($data as $line)
				$res[ trim($line[$this->_idField]) ] = $line;
		}

		// écriture de la donnée en cache
		$this->_setCache($cacheVarName, $res);

		// retour
		unset($encodedIds, $sql, $cacheVarName, $data, $encodedIds, $id, $mySort);
		return ($res);
	}
	
	/**
	 * Récupère tous les identifiants de la table.
	 * Attention au volume de donnees !
	 * 
	 * @return	array|null	Liste d'identifiants. null si rien trouve.
	 */
	public function getAllIds($sort=null) {
		// on cherche la donnée en cache
		{
			$cacheVarName = '__dao:' . $this->_dbName . ':' . $this->_tableName . ':getallIds';
			if (($data = $this->_getCache($cacheVarName)) !== null)
				return ($data);
		}

		// exécution de la requête
		{
			$sql = 'SELECT ' . $this->_idField . ' FROM ' . $this->getTableName() . ' ORDER BY ' . $this->_idField . ' ASC';
			$data = $this->queryAll($sql);
			if (empty($data)) {
				unset($cacheVarName, $data, $encodedIds, $sql);
				return (null);
			}
		}
		
		// indexation
		{
			$res = array();
			foreach($data as $line) {
				$myId = trim($line[$this->_idField]);
				$res[ $myId ] = $myId;
			}
		}

		// écriture de la donnée en cache
		$this->_setCache($cacheVarName, $res);

		// retour
		unset($encodedIds, $sql, $cacheVarName, $data, $encodedIds, $myId);
		return ($res);
	}
	
	/**
	 * Insère un élément dans la table.
	 * @param	array		$data		Hash contenant les informations champ => valeur à insérer.
	 * @return	int			La clé primaire de l'élément créé.
	 * @throws	Exception		Si l'insertion s'est mal déroulée.
	 */
	public function create($data) {
		// verifs
		if (!is_array($data) || empty($data))
			throw new \Exception("Cannot insert inexistant fields.");
		// effacement du cache pour cette DAO
		$this->_flushCache();
		
		// construction de la requete
		{
			$fields = array_keys($data);
			$values = array_values($data);
			foreach ($values as $key => $value)
				$values[$key] = $this->quote($value);
			
			$sql = " SELECT " . $this->_idField . " AS ID FROM NEW TABLE (
						INSERT INTO " . $this->getTableName() . " 
							(" . implode(", ", $fields) . ")
						VALUES (" . implode(", ", $values) . ")
					);";
			unset($data, $fields, $values);
		}
// if ($this->_tableName == 'HUB_HISTORY')
// die($sql);
		// envoi
		{
			$q1 = query_db2($sql, true);
			$results = result_db2($q1);
			if (empty($results)) {
				unset($sql, $q1, $results);
				return (null);
			}
		}

		// retour
		$res = $results['ID'];
		unset($sql, $q1, $results);
		return ($res);
	}
	
	/**
	 * Récupère des enregistrements à partir de critères de recherche.
	 * @param	array			$criterias	(optionnel) Critères de recherche. Ex: ['field1' => 'test', 'fields2' => ['test2','test3']] . Null par défaut, pour prendre tous les enregistrements.
	 * @param	string|array	$sort		(optionnel) Informations de tri. Ex : ['V4002_001' => 'asc'] .
	 * @param	int			$limitOffset	(optionnel) Décalage pour le premier élément retourné. 0 par défaut.
	 * @param	int			$nbrLimit	(optionnel) Nombre d'éléments maximum à retourner. null par défaut.
	 * @return	array	Liste de hashs.
	 */
	public function search($criterias=null, $sort=null, $limitOffset=null, $nbrLimit=null) {
		// generation du sql
		{
			// gestion du where
			{
				$where = '';
				if (!empty($criterias) && is_array($criterias))
				{
					$whereTmp = array();
					foreach ($criterias as $field => $criteria) {
						if (!is_array($criteria))
							$whereTmp[$field] = $field . ' = ' . $this->quote($criteria) . ' ';
						else {
							$tmp = array();
							foreach($criteria as $datum)
								$tmp[] = $this->quote($datum);
							$whereTmp[$field] = $field . " IN (" . implode(", ", $tmp) . ") ";
							unset($tmp);
						}
					}
					$where = ' WHERE ' . implode(' AND ', $whereTmp) . ' ';
					unset($whereTmp, $criteria, $tmp, $datum);
				}
			}
			// gestion du sort
			{
				$mySort = '';
				if (!is_null($sort)) {
					$sortList = array();
					if (is_string($sort))
						$sortList[] = $sort;
					else if ($sort == 'RAND()')
						$sortList[] = 'RAND()';
					else if (is_array($sort)) {
						foreach ($sort as $key => $value) {
							$field = is_int($key) ? $value : $key;
							if (is_array($this->_fields) && ($field2 = array_search($field, $this->_fields)) !== false && !is_int($field2))
								$field = $field2;
							$sortType = (!is_int($key) && (!strcasecmp($value, 'asc') || !strcasecmp($value, 'desc'))) ? $value : 'ASC';
							$sortList[] = "$field $sortType";
						}
					}
					if (!empty($sortList))
						$mySort = ' ORDER BY ' . implode(', ', $sortList) . ' ';
					unset($sortList, $field, $field2, $sortType);
				}
			}
			
			// gestion des limites
			{
				$mylimit = '';
				if (!empty($nbrLimit)) {
					if (empty($limitOffset))
						$limit = " FETCH FIRST $nbrLimit ROWS ONLY ";
					else
						$limit = " LIMIT $limitOffset, $nbrLimit ";
				}
			}
			
			// request
			$sql = 'SELECT ' . $this->_getFieldsString() . ' FROM ' . $this->getTableName() . $where . $mySort . $limit;
// die($sql);
			// retour
			unset($where, $mySort, $limit);
		}

		// prise du cache
		{
			$cacheVarName = '__dao:' . $this->_dbName . ':' . $this->_tableName . ':search:' . md5($sql);
			$data = $this->_getCache($cacheVarName);
			if ($data !== null) {
				unset($cacheVarName, $sql);
				return ($data);
			}
		}

		// exécution de la requête
		$data = $this->queryAll($sql);

		// indexation
		{
			$res = array();
			if (!empty($data)) {
				foreach ($data as $line) {
					if (!isset($line[$this->_idField])) {
						$res = $data;
						break;
					}
					$myId = $line[$this->_idField];
					$res[$myId] = $line;
				}
			}
			unset($line, $myId, $data);
		}

		// écriture de la donnée en cache
		$this->_setCache($cacheVarName, $res);

		// retour
		unset($cacheVarName, $sql);
		return ($res);
	}
	
	/**
	 * Récupère un enregistrements à partir de critères de recherche.
	 * @param	array			$criterias	(optionnel) Critères de recherche. Ex: ['field1' => 'test', 'fields2' => ['test2','test3']] . Null par défaut, pour prendre tous les enregistrements.
	 * @param	string|array	$sort		(optionnel) Informations de tri. Ex : ['V4002_001' => 'asc'] .
	 * @return	array	Hashs de l'enregistrement trouve.
	 */
	public function searchOne($criterias=null, $sort = null) {
 		// generation du sql
		{
			// gestion du where
			{
				$where = '';
				if (!empty($criterias) && is_array($criterias))
				{
					$whereTmp = array();
					foreach ($criterias as $field => $criteria) {
						if (!is_array($criteria))
							$whereTmp[$field] = $field . ' = ' . $this->quote($criteria) . ' ';
						else {
							$tmp = array();
							foreach($criteria as $datum)
								$tmp[] = $this->quote($datum);
							$whereTmp[$field] = $field . " IN (" . implode(", ", $tmp) . ") ";
							unset($tmp);
						}
					}
					$where = ' WHERE ' . implode(' AND ', $whereTmp) . ' ';
					unset($whereTmp, $criteria, $tmp, $datum);
				}
			}
			// gestion du sort
			{
				$mySort = '';
				if (!is_null($sort)) {
					$sortList = array();
					if (is_string($sort))
						$sortList[] = $sort;
					else if ($sort == 'RAND()')
						$sortList[] = 'RAND()';
					else if (is_array($sort)) {
						foreach ($sort as $key => $value) {
							$field = is_int($key) ? $value : $key;
							if (is_array($this->_fields) && ($field2 = array_search($field, $this->_fields)) !== false && !is_int($field2))
								$field = $field2;
							$sortType = (!is_int($key) && (!strcasecmp($value, 'asc') || !strcasecmp($value, 'desc'))) ? $value : 'ASC';
							$sortList[] = "$field $sortType";
						}
					}
					if (!empty($sortList))
						$mySort = ' ORDER BY ' . implode(', ', $sortList) . ' ';
					unset($sortList, $field, $field2, $sortType);
				}
			}
			
			// request
			$sql = 'SELECT ' . $this->_getFieldsString() . ' FROM ' . $this->getTableName() . $where . $mySort;
			// retour
			unset($where, $mySort);
		}
		
		// prise du cache
		{
			$cacheVarName = '__dao:' . $this->_dbName . ':' . $this->_tableName . ':searchOne:' . md5($sql);
			$data = $this->_getCache($cacheVarName);
			if ($data !== null) {
				unset($cacheVarName, $sql);
				return ($data);
			}
		}

		// exécution de la requête
		$data = $this->queryOne($sql);

		// écriture de la donnée en cache
		$this->_setCache($cacheVarName, $data);

		// retour
		unset($cacheVarName, $sql);
		return ($data);
	}

	/**
	 * Récupère des identifiants à partir de critères de recherche.
	 * @param	array	$criterias	(optionnel) Critères de recherche. Ex: ['field1' => 'test', 'fields2' => ['test2','test3']] . Null par défaut, pour prendre tous les enregistrements.
	 * @return	array	Liste d'identifiants.
	 */
	public function searchIds($criterias=null) {
		// generation du sql
		{
			// gestion du where
			{
				$where = '';
				if (!empty($criterias) && is_array($criterias))
				{
					$whereTmp = array();
					foreach ($criterias as $field => $criteria) {
						if (!is_array($criteria))
							$whereTmp[$field] = $field . ' = ' . $this->quote($criteria) . ' ';
						else {
							$tmp = array();
							foreach($criteria as $datum)
								$tmp[] = $this->quote($datum);
							$whereTmp[$field] = $field . " IN (" . implode(", ", $tmp) . ") ";
							unset($tmp);
						}
					}
					$where = ' WHERE ' . implode(' AND ', $whereTmp) . ' ';
					unset($whereTmp, $criteria, $tmp, $datum);
				}
			}
			
			// request
			$sql = 'SELECT ' . $this->_idField . ' FROM ' . $this->getTableName() . $where . ' ORDER BY ' . $this->_idField . ' ASC';
			// retour
			unset($where);
		}

		// prise du cache
		{
			$cacheVarName = '__dao:' . $this->_dbName . ':' . $this->_tableName . ':search:' . md5($sql);
			$data = $this->_getCache($cacheVarName);
			if ($data !== null) {
				unset($cacheVarName, $sql);
				return ($data);
			}
		}

		// exécution de la requête
		$data = $this->queryAll($sql);

		// indexation
		{
			$res = array();
			if (!empty($data)) {
				foreach ($data as $line) {
					$myId = $line[$this->_idField];
					$res[$myId] = $myId;
				}
			}
			unset($line, $myId, $data);
		}

		// écriture de la donnée en cache
		$this->_setCache($cacheVarName, $res);

		// retour
		unset($cacheVarName, $sql);
		return ($res);
	}
	
	/**
	 * Met à jour un ou plusieurs enregistrements.
	 * @param	mixed	$id		Identifiant de l'enregistrement à modifier.
	 * @param	array	$fields		Hash contenant des paires champ => valeur à mettre à jour.
	 * @throws	\Temma\Exceptions\DaoException	Si les critères ou les champs/valeurs sont mal formés.
	 */
	public function update($id, $fields) {
		// effacement du cache pour cette DAO
		$this->_flushCache();
		// constitution de la requête
		{
			// Construction des sets
			{
				$set = array();
				foreach ($fields as $field => $value) {
					if (is_string($value) || is_int($value) || is_float($value))
						$set[] = "$field = " . $this->quote($value);
					else if (is_bool($value))
						$set[] = "$field = '" . ($value ? 1 : 0) . "'";
					else if (is_null($value))
						$set[] = "$field = NULL";
					else
						throw new \Exception("Bad field '$field' value.");
				}
			}

			$sql = 'UPDATE ' . $this->getTableName() . '
				SET ' . implode(',', $set) . '
				WHERE ' . $this->_idField . " = " . $this->quote($id);
			unset($set);
		}
//if ($this->_tableName == 'HUB_ACCOUNTS')
//die($sql);
		// execution
		query_db2($sql, true);
		
		// retour
		unset($sql);
	}

	/**
	 * Efface un enregistrement.
	 * @param	int	$id		Identifiant de l'élément à effacer.
	 */
	public function remove($id) {
		// effacement du cache pour cette DAO
		$this->_flushCache();
		// constitution et exécution de la requête
		$sql = 'DELETE FROM ' . $this->getTableName() . ' WHERE ' . $this->_idField . " = " . $this->quote($id) . '';
		$this->execute($sql);
		// return
		unset($sql);
	}

	/**
	 * Efface des enregistrements.
	 * @param	int	$ids		Identifiants à effacer.
	 */
	public function removeFromIds($ids) {
		// verif
		if (empty($ids) || !is_array($ids))
			return;
		// effacement du cache pour cette DAO
		$this->_flushCache();
		// encodage
		$myIds = [];
		foreach ($ids as $myId)
			$myIds[] = $this->quote($myId);
		// constitution et exécution de la requête
		$sql = 'DELETE FROM ' . $this->getTableName() . ' WHERE ' . $this->_idField . " IN (" . implode(', ', $myIds) . ')';
		$this->execute($sql);
		// return
		unset($sql, $myIds, $myId);
	}

	
	/** 
	 * Retourne le dernier ID insere dans cette tqble.
	 * @return int|null		Identifiant de la derniere ligne inseree. Null si rien trouve.
	 */
	protected function lastInsertedId() {
		// envoi de la request
		{
			$sql = "SELECT IDENTITY_VAL_LOCAL() as ID FROM ".$this->getTableName()." FETCH FIRST 1 ROWS ONLY";
			$q1 = query_db2($sql, true);
			$results = result_db2($q1);
		}
		
		// analyse des resultats
		{
			if (empty($results)) {
				unset($sql, $q1, $results);
				return (null);
			}
			$res = $results['ID'];
		}
		
		// retour
		unset($sql, $q1, $results);
		return ($res);
	}
	/** 
	 * Encrypte un element.
	 * @param string|int	$elem	Element a encrypter
	 */
	public function quote($elem) {
		if ($elem === 0 || $elem === '0')
			return ("'0'");
		if (empty($elem))
			return ("''");
		if ($elem == 'NOW()')
			return ("NOW()");
		if ($elem == 'NULL')
			return ("NULL");
		if (is_int($elem) || ctype_digit($elem))
			return ("'$elem'");
		// encryption d'une string
		// @todo : utiliser mysqli_real_escape_string() quand disponible !
		$elem = mysqlsafe($elem);
		return ("'$elem'");
	}

	/**
	 * Retourne le nom d'un champ de la table, en fonction de la présence ou non d'alias.
	 * Cette méthode ne devrait être utilisée que par les objets de type \Temma\DaoCriteria.
	 * @param	string	$field	Le nom du champ.
	 * @return	string	Le nom du champ, avec traitement des alias.
	 */
	public function getFieldName($field) {
		if (empty($this->_fields))
			return ($field);
		$realName = array_search($field, $this->_fields);
		return ($realName ? $realName : $field);
	}

	/**
	 * Retourne le nom de la table.
	 * @return	string	Le nom etendu de la table.
	 */
	public function getTableName() {
		$dbName = empty($this->_dbName) ? '' : ($this->_dbName . '.');
		$tableName = $dbName . $this->_tableName;
		return ($tableName);
	}
	
	/* ***************** GESTION DU CACHE ************* */
	/**
	 * Désactive le cache.
	 * @param	mixed	$p	(optionnel) Valeur à retourner.
	 * @return	\Temma\Dao	L'instance de l'objet courant.
	 */
	public function disableCache($p) {
		$this->_disableCache = true;
		return (is_null($p) ? $this : $p);
	}
	/**
	 * Active le cache.
	 * @param	mixed	$p	(optionnel) Valeur à retourner.
	 * @return	\Temma\Dao	L'instance de l'objet courant.
	 */
	public function enableCache($p=null) {
		$this->_disableCache = false;
		return (is_null($p) ? $this : $p);
	}

	/* ****** Méthodes privées ****** */
	/**
	 * Génère la chaîne de caractères contenant la liste des champs.
	 * @return	string	La chaîne.
	 */
	protected function _getFieldsString() {
		if (!empty($this->_fieldsString))
			return ($this->_fieldsString);
		if (empty($this->_fields))
			$this->_fieldsString = '*';
		else {
			$list = array();
			foreach ($this->_fields as $fieldName => $aliasName) {
				if (is_int($fieldName))
					$list[] = $aliasName;
				else
					$list[] = "`$fieldName` AS $aliasName";
			}
			$this->_fieldsString = implode(', ', $list);
		}
		return ($this->_fieldsString);
	}
	/**
	 * Lit une donnée en cache.
	 * @param	string	$cacheVarName	Nom de la variable.
	 */
	protected function _getCache($cacheVarName) {
		if (!$this->_cache || $this->_disableCache)
			return (null);
		return ($this->_cache->get($cacheVarName));
	}
	/**
	 * Ajoute une variable en cache.
	 * @param	string	$cacheVarName	Nom de la variable.
	 * @param	mixed	$data		La donnée à mettre en cache.
	 */
	protected function _setCache($cacheVarName, $data) {
		if (!$this->_cache || $this->_disableCache)
			return;
		$listName = '__dao:' . $this->_dbName . ':' . $this->_tableName;
		$list = $this->_cache->get($listName);
		$list[] = $cacheVarName;
		$this->_cache->set($listName, $list);
		$this->_cache->set($cacheVarName, $data);
	}
	/** Efface toutes les variables de cache correspondant à cette DAO. */
	protected function _flushCache() {
		$listName = '__dao:' . $this->_dbName . ':' . $this->_tableName;
		if (!$this->_cache || $this->_disableCache || ($list = $this->_cache->get($listName)) === null || !is_array($list))
			return;
		foreach ($list as $var)
			$this->_cache->set($var, null);
		$this->_cache->set($listName, null);
	}

	/**
	 * Retourne une chaine de $length characteres completee par un caractere de completion.
	 * @param	string	$text	Texte de base. Ex : '1'.
	 * @param	int 	$length	Taille de la chaine finale.
	 * @param	char 	$completion	Caractere de completion.
	 * @return	string	Chaine compoletee.
	 *
	 * Exemple de code : echo $this->_expandTo('1', 4, '0'); => '0001'
	 */
	public function _expandTo($text, $length, $completion='0') {
		$res = $text;
		if (strlen($res) > $length)
			return ($res);
		while (strlen($res) < $length)
			$res = $completion . $res;
		return ($res);
	}
	/**
	 * Get all array field value.
	 * @param	array	$haystack	Array to search on.
	 * @param	string 	$fieldName	Array field name.
	 * @return	array	All field values.
	 *
	 * Example : 
	 * $t = [['f1'=>'a', 'f2'=>1], ['f1'=>'c', 'f2'=>1], ['f1'=>'b', 'f2'=>1]];
	 * $res = $this->_getValues($t, 'f1');
	 * print($res);
	 *
	 * => ['a', 'c', 'b']
	 */
	public function _getValues($haystack, $fieldName) {
		// verifs
		if (!is_array($haystack) || empty($haystack) || empty($fieldName))
			return (array());
		// search
		{
			$res = array();
			foreach ($haystack as $line) {
				if (!isset($line[$fieldName]))
					continue;
				$res[$line[$fieldName]] = $line[$fieldName];
			}
			unset($line);
		}
		// return
		return ($res);
	}

	/**
	 * Get all different value contained on a field.
	 * @param	string	$fieldName	Database field name. Ex: 'ID'.
	 * @param	array	$criterias	Search criterias. Ex : ['mandant' => '506', 'field2' => 'value2', ...]
	 * @return	array	found values. indexed.
 	 */
	public function getFieldValues($fieldName, $criterias=null) {
		// where
		{
			$where = [];
			if (!empty($criterias)) {
				foreach($criterias as $fieldTmp => $valueTmp) {
					$where[] = $fieldTmp . ' = ' . $this->quote($valueTmp);
				}
			}
		}
		
		// sql
		{
			$sql = 'SELECT DISTINCT ' . $fieldName . ' AS VALUE FROM ' . $this->getTableName() . ' ';
			if (!empty($where)) {
				$sql .= ' WHERE ' . implode(' AND ', $where) . ' ';
			}
			$results = $this->queryAll($sql);
			if (empty($results)) {
				unset($where, $sql, $results);
				return [];
			}
		}
		
		// indexation
		{
			$res = [];
			foreach ($results as $line) {
				$res[$line['VALUE']] = $line['VALUE'];
			}
		}
		
		// return
		unset($where, $sql, $results, $line);
		return ($res);
	}
}

?>
