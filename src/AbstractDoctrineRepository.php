<?php

namespace mhndev\doctrineRepository;

use Abstracts\Repository\Exceptions\InvalidLimitNumber;
use Abstracts\Repository\Exceptions\InvalidSortTypeException;
use Abstracts\Repository\Exceptions\RepositoryException;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class AbstractDoctrineRepository
 * @package Abstracts\Repository
 */
abstract class AbstractDoctrineRepository extends EntityRepository implements iRepository
{

    /**
     * @var
     */
    protected $data;

    /**
     * @var array
     */
    protected $with = [];

    /**
     * @var array
     */
    protected $columns = ['*'];

    /**
     * @var
     */
    protected $orderBy;

    /**
     * @var string
     */
    protected $sortMethod = 'DESC';

    /**
     * @var int
     */
    protected $limit = 10;

    /**
     * @var int
     */
    protected $offset = 0;

    /**
     * @var int
     */
    protected $page = 1;


    /**
     * @var string
     */
    protected $entity;


    /**
     * @var QueryBuilder
     */
    protected $query;

    /**
     * @return mixed
     */
    public function entity()
    {
        if ($this->entity) {
            return $this->entity;
        } else {
            return new $this->_entityName;
        }
    }


    /**
     * @return string
     */
    public function getEntityClass()
    {
        return $this->_entityName;
    }

    /**
     * @param integer $page
     * @return $this
     */
    public function setPage($page)
    {
        $this->page = $page;

        return $this;
    }

    /**
     * @param integer $page
     * @return integer
     */
    public function page($page = null)
    {
        if ($page) {
            return $this->page = $page;
        }

        if ($this->page) {
            return $this->page;
        } elseif (!empty($_GET['page'])) {
            return $this->page = $_GET['page'];
        } else {
            return $this->page = 1;
        }
    }

    /**
     * @param $value
     * @return array
     */
    public function findOneByIdForValidation($value)
    {
        dump($value);
        die();

        return $this->findBy($value);
    }

    /**
     * @param $value
     * @throws EntityNotFoundException
     */
    public function findOneByIdUpdate($value)
    {
        die($value);

        dump($value);

        $result = $this->findOneBy($value);

        dump($result);
        die();
    }

    /**
     * Finds a single entity by a set of criteria.
     *
     * @param array $criteria
     * @param array|null $orderBy
     * @return object The entity instance or NULL if the entity can not be found.
     * @throws EntityNotFoundException
     */
    public function findOneBy(array $criteria, array $orderBy = null)
    {

        $result = parent::findOneBy($criteria, $orderBy = null);

        if ($result == null) {

            throw new EntityNotFoundException;
        } else {
            return $result;
        }
    }


    /**
     * @return QueryBuilder
     */
    protected function makeQuery()
    {
        $this->query = $this->query ? $this->query : $this->createQueryBuilder('e')->select('e');

        return $this->query;
    }

    /**
     * @param bool $returnArray
     * @return array
     */
    public function all($returnArray = true)
    {
        $query = ($this->query) ? $this->query : $this->makeQuery();

        return $returnArray ? $this->arrayOfEntitiesToArray($query->getQuery()->getResult()) : $query->getQuery()->getResult();
    }


    protected function arrayOfEntitiesToArray($array)
    {
        $result = [];
        foreach ($array as $entity){
            $result[] = $entity->with($this->with)->toArray();
        }

        return $result;
    }

    /**
     * @param null $perPage
     * @return Paginator
     * @throws EntityNotFoundException
     * @throws InvalidLimitNumber
     */
    public function paginate($perPage = null)
    {
        if ($perPage) {
            $this->limit($perPage);
        }

        $query = $this->query ? $this->query : $this->makeQuery();

        return $this->customPaginate($query);
    }

    /**
     * @param Query|QueryBuilder $dql
     * @param bool $returnArray
     * @return Paginator
     * @throws EntityNotFoundException
     */
    protected function customPaginate($dql, $returnArray = true)
    {
        $paginator = new Paginator($dql);

        $query = $paginator
            ->getQuery()
            ->setFirstResult($this->limit * ($this->page() - 1))
            ->setMaxResults($this->limit);

//        if ($returnArray) {
//            $query->setHydrationMode(Query::HYDRATE_ARRAY);
//        }

        $total = count($paginator);
        $page = $this->page();

        $from = (($this->page - 1) * $this->limit) + 1;
        $to = min($total, $from + $this->limit - 1);
        $next = ($total > $to) ? "/?page=" . ($page + 1) : null;
        $prev = ($from > 1) ? "/?page=" . ($page - 1) : null;


//        if (empty($paginator->getIterator()->getArrayCopy())) {
//            throw new EntityNotFoundException;
//        }

        $result = $paginator->getQuery()->getResult();


        foreach ($result as $key => $value) {
            if (is_object($value)) {

                $result[$key] = $value->toArray(true);
            }
        }


        $this->data['pagination']['total'] = $total;
        $this->data['pagination']['to'] = $to;
        $this->data['pagination']['from'] = $from;
        $this->data['pagination']['per_page'] = $this->limit;
        $this->data['pagination']['current_page'] = $this->page();
        $this->data['pagination']['next_page_url'] = $next;
        $this->data['pagination']['prev_page_url'] = $prev;
        $this->data['data'] = $result;

        return $this->data;
    }

    /**
     * @param $array
     * @param $character
     * @return array
     */
    protected function makeSelect($array, $character)
    {
        $result = [];

        foreach ($array as $column) {
            $result[] = $character . '.' . $column;
        }

        return $result;
    }


    /**
     * @param $key
     * @param $value
     * @param bool $returnArray
     * @param bool $throwException
     * @return mixed
     * @throws EntityNotFoundException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findOneByCriteria($key, $value = null, $returnArray = true, $throwException = true)
    {
        $query = $this->createQueryByCriteria($key, $value);

        if ($returnArray) {
            $query->setHydrationMode(QUERY::HYDRATE_ARRAY);
        }

        $result = $query->getOneOrNullResult();

        if ($result == null && $throwException) {
            throw new EntityNotFoundException;
        } elseif ($result == null) {
            return null;
        }

        return $result;
    }


    /**
     * @param $key
     * @param null $value
     * @param bool $or
     * @return Query
     * @throws \Doctrine\Common\Persistence\Mapping\MappingException
     */
    protected function createQueryByCriteria($key, $value = null, $or = false)
    {
        $q = $this->makeQuery();

        $chars = ['f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n'];
        $i = 0;

        $relations = $this->getRelations();

        if (!empty($this->with)) {
            $existingAssociations = $this->_em->getMetadataFactory()->getMetadataFor($this->_entityName)->getAssociationNames();

            $associations = array_intersect($this->with, $existingAssociations);

            foreach ($associations as $association) {
                $q = $q->join('e.' . $association, $chars[$i]);
                $i++;
            }
        }


        if (is_array($key) || empty($value)) {
            foreach ($key as $field => $value) {

                if (in_array($field, $relations)) {
                    if ($or) {
                        $q->join('e.' . $field, $field)
                            ->addSelect($field)
                            ->orWhere(sprintf('e.%s = :%s', $field, $field))->setParameter($field, $value);
                    } else {
                        $q->join('e.' . $field, $field)
                            ->addSelect($field)
                            ->andWhere(sprintf('e.%s = :%s', $field, $field))->setParameter($field, $value);
                    }
                } else {
                    if ($or) {
                        $q->orWhere(sprintf('e.%s = :%s', $field, $field))->setParameter($field, $value);
                    } else {
                        $q->andWhere(sprintf('e.%s = :%s', $field, $field))->setParameter($field, $value);
                    }
                }


            }
        } elseif (!empty($value)) {
            if (in_array($key, $relations)) {
                $q->join('e.' . $key, $key)
                    ->andWhere('e.' . $key . ' = :value')
                    ->addSelect($key)
                    ->setParameter('value', $value);


            } else {
                $q->where(sprintf('e.%s = :%s', $key, $key))
                    ->setParameter($key, $value);
            }

        }

        $query = $q->getQuery();

        foreach ($this->with as $with) {
            $query->setFetchMode($this->_entityName, $with, ClassMetadata::FETCH_EAGER);
        }

        return $query;
    }


    /**
     * @param $key
     * @param null $value
     * @param bool $returnArray
     * @return Paginator
     * @throws EntityNotFoundException
     * @throws \Doctrine\Common\Persistence\Mapping\MappingException
     */
    public function findManyByCriteria($key, $value = null, $returnArray = true)
    {
        $q = $this->makeQuery();

        $chars = ['f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n'];
        $i = 0;

        if (!empty($this->with)) {
            $existingAssociations = $this->_em->getMetadataFactory()->getMetadataFor($this->_entityName)->getAssociationNames();

            $associations = array_intersect($this->with, $existingAssociations);

            foreach ($associations as $association) {
                $q = $q->join('e.' . $association, $chars[$i]);
                $i++;
            }
        }

        if (is_array($key) || empty($value)) {
            foreach ($key as $field => $value) {
                $q->andWhere(sprintf('e.%s = :%s', $field, $field))
                    ->setParameter($field, $value);
            }
        } elseif (!empty($value)) {
            $q->andWhere(sprintf('e.%s = :%s', $key, $key))
                ->setParameter($key, $value);
        }

        $query = $q->getQuery();
        foreach ($this->with as $with) {
            $query->setFetchMode($this->_entityName, $with, ClassMetadata::FETCH_EAGER);
        }

        $query->setHydrationMode(QUERY::HYDRATE_ARRAY);

        return $this->customPaginate($query, $returnArray);
    }

    public function findManyByCriteriaObjectResult($key, $value = null)
    {
        $q = $this->makeQuery();

        $chars = ['f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n'];
        $i = 0;

        if (!empty($this->with)) {
            $existingAssociations = $this->_em->getMetadataFactory()->getMetadataFor($this->_entityName)->getAssociationNames();

            $associations = array_intersect($this->with, $existingAssociations);

            foreach ($associations as $association) {
                $q = $q->join('e.' . $association, $chars[$i]);
                $i++;
            }
        }

        if (is_array($key) || empty($value)) {
            foreach ($key as $field => $value) {
                $q->andWhere(sprintf('e.%s = :%s', $field, $field))
                    ->setParameter($field, $value);
            }
        } elseif (!empty($value)) {
            $q->andWhere(sprintf('e.%s = :%s', $key, $key))
                ->setParameter($key, $value);
        }

        $query = $q->getQuery();
        foreach ($this->with as $with) {
            $query->setFetchMode($this->_entityName, $with, ClassMetadata::FETCH_EAGER);
        }

        return $query->getResult();
    }


    /**
     * @param array $data
     * @return mixed
     */
    public function create(array $data)
    {
        $entity = $this->toObject($data);

        $this->getEntityManager()->persist($entity);
        $this->getEntityManager()->flush();
        return $entity;
    }

    /**
     * @param array $data
     * @return mixed
     */
    public function toObject(array $data)
    {
        $entityClassName = ucfirst($this->_entityName);
        $entity = new $entityClassName;

        $entity = $this->updateEntity($entity, $data);

        return $entity;
    }

    /**
     * @param $id
     * @param array $data
     * @return null|object
     */
    public function update($id, array $data)
    {
        $entity = $this->findOneById($id, false);

        $entity = $this->updateEntity($entity, $data);
        $this->getEntityManager()->flush();

        return $entity;
    }


    /**
     * @return mixed
     */
    public function getRelations()
    {
        $relations = $this->getClassMetadata()->getAssociationMappings();
        $relation_names = array_keys($relations);

        return $relation_names;
    }


    /**
     * @param mixed $entity
     * @param array $data
     * @return mixed
     */
    protected function updateEntity($entity, array $data)
    {
        $relations = $this->getClassMetadata()->getAssociationMappings();
        $relation_names = array_keys($relations);


        foreach ($data as $key => $value) {

            if (!empty($relation_names) && (in_array($key, $relation_names) || in_array(lcfirst($this->_snakeToCamel($key)), $relation_names)) ){

                $relatedEntity = $this->getEntityManager()->find($relations[lcfirst($this->_snakeToCamel($key))]['targetEntity'], $value);
                $entity->{'set' . ucfirst($this->_snakeToCamel($key))}($relatedEntity);
            } else {
                $entity->{'set' . ucfirst($this->_snakeToCamel($key))}($value);
            }
        }

        return $entity;
    }


    /**
     * @param $val
     * @return mixed
     */
    protected function _snakeToCamel($val)
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $val)));
    }

    /**
     * Delete an item by id
     * @param $id
     */
    public function delete($id)
    {
        $entity = $this->find($id);

        $this->getEntityManager()->remove($entity);

        $this->getEntityManager()->flush();
    }

    /**
     * @param array|string $with
     * @return $this
     * @throws RepositoryException
     */
    public function with($with = [])
    {
        if (is_array($with) === false) {
            $with = [$with];
        }

        $this->with = $with;

        return $this;
    }

    /**
     * @param array $columns
     * @return $this
     * @throws RepositoryException
     */
    public function columns(array $columns = ['*'])
    {
        if (is_array($columns) === false) {
            throw new RepositoryException;
        }

        $this->columns = $columns;

        return $this;
    }

    /**
     * @param int $limit
     * @return $this
     * @throws InvalidLimitNumber
     */
    public function limit($limit = 10)
    {
        if (!is_numeric($limit) || $limit < 1) {
            throw new InvalidLimitNumber;
        }

        $this->limit = $limit;

        return $this;
    }


    /**
     * @param $orderBy
     * @param string $sort
     * @return $this
     * @throws InvalidSortTypeException
     */
    public function orderBy($orderBy, $sort = 'DESC')
    {
        if ($orderBy === null) {
            return $this;
        }

        $this->orderBy = $orderBy;

        if (!in_array(strtoupper($sort), ['DESC', 'ASC'])) {
            throw new InvalidSortTypeException;
        }

        $this->sortMethod = $sort;

        return $this;
    }

    /**
     * @param $id
     * @param bool $returnArray
     * @return mixed
     * @throws EntityNotFoundException
     */
    public function findOneById($id, $returnArray = true)
    {
        if (is_null($id)) {
            throw new EntityNotFoundException;
        }

        $entity = $this->findOneByCriteria('id', $id, $returnArray);

        return $entity;
    }

    /**
     * @param $key
     * @param $value
     * @return array
     */
    public function findManyBy($key, $value)
    {
        $result = $this->findBy([$key => $value], $this->orderBy, $this->limit, $this->offset);

        return $result;
    }

    /**
     * @param array $ids
     * @param bool $returnArray
     * @return mixed
     * @throws EntityNotFoundException
     * @throws \Doctrine\ORM\Mapping\MappingException
     */
    public function findManyByIds(array $ids, $returnArray = true)
    {
        $meta = $this->getEntityManager()->getClassMetadata(get_class($this->entity()));
        $identifier = $meta->getSingleIdentifierFieldName();


        $q = $this->makeQuery();

        $q->andWhere('e.' . $identifier . ' IN (:ids)')
            ->setParameter('ids', $ids);

        return $this->customPaginate($q, $returnArray);

    }


    /**
     * @param $key
     * @param $value
     * @param bool $or
     * @param int $perPage
     * @return mixed
     * @throws EntityNotFoundException
     */
    public function paginateBy($key, $value, $or = false, $perPage = 10)
    {
        $query = $this->createQueryByCriteria($key, $value, $or);

        return $this->customPaginate($query, false);
    }

    /**
     * @param $id
     * @param array $data
     * @return boolean
     */
    public function updateOneById($id, array $data = [])
    {
        return $this->update($id, $data);
    }

    /**
     * @param $key
     * @param $value
     * @param array $data
     * @return boolean
     */
    public function updateOneBy($key, $value, array $data = [])
    {
        $object = $this->findOneByCriteria($key, $value, false);

        foreach ($data as $key => $value) {
            $object->{$this->_snakeToCamel('set_' . $key)}($value);
        }

        $this->getEntityManager()->flush();
    }

    /**
     * @param $key
     * @param $value
     * @param array $data
     * @return boolean
     */
    public function updateManyBy($key, $value, array $data = [])
    {
        $result = $this->findManyByCriteria($key, $value, false);

        foreach ($result as $object) {
            foreach ($data as $key => $value) {
                $object->{$this->_snakeToCamel('set_' . $key)}($value);
            }
        }

        $this->getEntityManager()->flush();
    }

    /**
     * @param array $ids
     * @param array $data
     * @return bool
     * @throws EntityNotFoundException
     */
    public function updateManyByIds(array $ids, array $data = [])
    {
        foreach ($ids as $id) {
            $entity = $this->find($id);

            if ($entity) {
                foreach ($data as $key => $value) {
                    $entity->{'set' . ucfirst($key)}($value);
                }
            } else {
                throw new EntityNotFoundException;
            }

        }

        $this->getEntityManager()->flush();
    }

    /**
     * @param $id
     * @return boolean
     */
    public function deleteOneById($id)
    {
        $this->delete($id);
    }

    /**
     * @param array $ids
     * @return bool
     */
    public function allExist(array $ids)
    {
        // TODO: Implement allExist() method.
    }

    /**
     * @param $key
     * @param $value
     * @return boolean
     */
    public function deleteOneBy($key, $value = null)
    {
        $item = $this->findOneByCriteria($key, $value, false);

        $this->getEntityManager()->remove($item);

        $this->getEntityManager()->flush();
    }


    /**
     * @param $key
     * @param $value
     * @return boolean
     */
    public function deleteManyBy($key, $value = null)
    {
        $result = $this->findManyByCriteriaObjectResult($key, $value);


        foreach ($result as $object) {
            $this->getEntityManager()->remove($object);
        }

        $this->getEntityManager()->flush();
    }

    /**
     * @param array $ids
     * @return mixed
     */
    public function deleteManyByIds(array $ids)
    {
        foreach ($ids as $id) {
            $entity = $this->find($id);
            $this->getEntityManager()->remove($entity);
        }

        $this->getEntityManager()->flush();
    }

    /**
     * @param $id
     * @param $field
     * @return bool
     */
    public function inc($id, $field)
    {
        $entity = $this->findOneById($id, false);

        $newFieldValue = $entity->{lcfirst($this->_snakeToCamel('get_' . $field))}() + 1;

        return $this->updateOneById($id, [$field => $newFieldValue]);
    }

    /**
     * @param $id
     * @param $field
     * @return bool
     */
    public function dec($id, $field)
    {
        $entity = $this->findOneById($id, false);
        $newFieldValue = $entity->{$this->_snakeToCamel('get' . $field)} - 1;

        return $this->updateOneById($id, [$field => $newFieldValue]);
    }

    /**
     * @param array $credentials
     * @param array $data
     * @return boolean
     */
    public function updateOneByCriteria(array $credentials, array $data = [])
    {
        // TODO: Implement updateOneByCriteria() method.
    }

    /**
     * @param array $credentials
     * @param array $data
     * @return boolean
     */
    public function updateManyByCriteria(array $credentials = [], array $data = [])
    {
        // TODO: Implement updateManyByCriteria() method.
    }

    /**
     * @param array|string $key
     * @param null|string $value
     * @return bool
     */
    public function deleteOneByCriteria($key, $value = null)
    {
        $this->deleteOneBy($key, $value);
    }

    /**
     * @param array $credentials
     * @return boolean
     */
    public function deleteManyByCriteria(array $credentials = [])
    {
        $this->deleteManyBy($credentials);
    }

    /**
     * @param array $credentials
     * @param       $perPage
     *
     * @return mixed
     */
    public function searchByCriteria(array $credentials = [], $perPage)
    {
        // TODO: Implement searchByCriteria() method.
    }


    /**
     * @param string $key
     * @param string $value
     * @param string $operator
     *
     * @return QueryBuilder
     */
    public function where($key, $value, $operator = '=')
    {
        $query = $this->makeQuery();

        $operator = strtoupper($operator);

        $relations = $this->getRelations();

        if(in_array($key, $relations)){
            $this->query = $query->join('e.' . $key, $key)
                ->addSelect($key)
                ->andWhere(sprintf('e.%s = :%s', $key, $key))
                ->setParameter($key, $value);

        }else{
            $value = ($operator == 'LIKE') ? '%'.$value.'%' : $value;

            $this->query = $query->andWhere("e.$key $operator :value")
                ->setParameter('value', $value);
        }

        return $this;
    }


    /**
     * @param string $key
     * @param array $values
     *
     * @return $this
     */
    public function whereIn($key, array $values)
    {
        $relations = $this->getRelations();
        $query = $this->makeQuery();


        if(in_array($key, $relations)){
            $this->query = $query->innerJoin("e.$key",'uuu')
                ->where("uuu.id IN(:values)")
                ->setParameter('values', array_values($values));
        }else{
            $this->query = $query->where("e.$key IN(:values)")
                ->setParameter('values', array_values($values));
        }

        return $this;
    }

//    /**
//     * @param callable $callable
//     * @return $this
//     */
//    public function whereClosure(Callable $callable)
//    {
//        $callable($this->query);
//
//        return $this;
//    }


    /**
     * @param Request $request
     * @return Paginator
     */
    public function search(Request $request)
    {
        $searchQuery = $this->createQueryBuilder('R');

        $this->prepareParams($searchQuery, $request);
        if (!is_null($request->query->get('with'))) {
            $this->prepareJoins($searchQuery, $request);
        }

//        dump($searchQuery->getQuery()->getResult());
//        die();

        return $this->customPaginate($searchQuery->getQuery(), false);


        //$result = $this->getEntityManager()->getE

    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param Request $request
     */
    private function prepareParams(QueryBuilder $queryBuilder, Request $request)
    {
        $firstQuery = true;

        if (!empty($request->query->get('search'))) {
            $searchItems = explode(',', $request->query->get('search'));

            foreach ($searchItems as $item) {

                $query = (explode(':', $item));
                if ($firstQuery) {
                    $queryBuilder->where('R.' . $query[0] . " " . $query[1] . ' :' . $query[0]);
                    $firstQuery = false;
                } else {
                    $queryBuilder->andWhere('R.' . $query[0] . " " . $query[1] . ' :' . $query[0]);

                }

            }

            $searchItems = explode(',', $request->query->get('search'));
            foreach ($searchItems as $item) {
                $parameter = explode(':', $item);

                $queryBuilder->setParameter($parameter[0], $parameter[2]);
            }

        }


    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param Request $request
     */
    private function prepareJoins(QueryBuilder $queryBuilder, Request $request)
    {
        $chars = ['q', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z'];
        $counter = 0;

        foreach (explode(',', $request->query->get('with')) as $with) {
            $queryBuilder->join('R.' . $with, $chars[$counter]);
            $counter++;
        }
    }

}
