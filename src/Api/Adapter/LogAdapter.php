<?php
namespace Log\Api\Adapter;

use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query\Expr\Comparison;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Stdlib\ErrorStore;

class LogAdapter extends AbstractEntityAdapter
{
    protected $sortFields = [
        'id' => 'id',
        'user' => 'user',
        'job' => 'job',
        'reference' => 'reference',
        'severity' => 'severity',
        'created' => 'created',
    ];

    public function getResourceName()
    {
        return 'logs';
    }

    public function getRepresentationClass()
    {
        return \Log\Api\Representation\LogRepresentation::class;
    }

    public function getEntityClass()
    {
        return \Log\Entity\Log::class;
    }

    public function hydrate(Request $request, EntityInterface $entity,
        ErrorStore $errorStore
    ) {
        switch ($request->getOperation()) {
            case Request::CREATE:
                $data = $request->getContent();
                if (empty($data['o:user'])) {
                    $user = null;
                } elseif (is_object($data['o:user'])) {
                    $user = $data['o:user'];
                } else {
                    $user = $this->getAdapter('users')->findEntity($data['o:user']['o:id']);
                }
                if (empty($data['o:job'])) {
                    $job = null;
                } elseif (is_object($data['o:job'])) {
                    $job = $data['o:job'];
                } else {
                    $job = $this->getAdapter('jobs')->findEntity($data['o:job']['o:id']);
                }
                $entity->setUser($user);
                $entity->setJob($job);
                $entity->setReference($data['o:reference']);
                $entity->setSeverity($data['o:severity']);
                $entity->setMessage($data['o:message']);
                $entity->setContext($data['o:context']);
                $entity->setCreated(new \DateTime('now'));
                break;
        }
    }

    public function buildQuery(QueryBuilder $qb, array $query)
    {
        if (isset($query['user_id']) && strlen($query['user_id'])) {
            $alias = $this->createAlias();
            $qb->innerJoin(
                $this->getEntityClass() . '.user',
                $alias
            );
            $qb->andWhere($qb->expr()->eq(
                $alias . '.id',
                $this->createNamedParameter($qb, $query['user_id']))
            );
        }

        if (isset($query['job_id']) && strlen($query['job_id'])) {
            $alias = $this->createAlias();
            $qb->innerJoin(
                $this->getEntityClass() . '.job',
                $alias
            );
            $qb->andWhere($qb->expr()->eq(
                $alias . '.id',
                $this->createNamedParameter($qb, $query['job_id']))
            );
        }

        if (isset($query['reference']) && strlen($query['reference'])) {
            $qb->andWhere($qb->expr()->eq(
                $this->getEntityClass() . '.reference',
                $this->createNamedParameter($qb, $query['reference']))
            );
        }

        // TODO Allow to search severity by standard name.
        if (isset($query['severity']) && strlen($query['severity'])) {
            $this->buildQueryComparison($qb, $query, $query['severity'], 'severity');
        }

        // TODO Remove severity_min and severity_max here and replace them by a javascript.
        if (isset($query['severity_min']) && strlen($query['severity_min'])) {
            $this->buildQueryComparison($qb, $query, '<=' . $query['severity_min'], 'severity');
        }
        if (isset($query['severity_max']) && strlen($query['severity_max'])) {
            $this->buildQueryComparison($qb, $query, '>=' . $query['severity_max'], 'severity');
        }

        if (isset($query['created']) && strlen($query['created'])) {
            $this->buildQueryComparison($qb, $query, $query['created'], 'created');
        }
    }

    /**
     * Add a comparison condition to query from a value containing an operator.
     *
     * @param QueryBuilder $qb
     * @param array $query
     * @param string $value
     * @param string $column
     */
    protected function buildQueryComparison(QueryBuilder $qb, array $query, $value, $column)
    {
        preg_match('/^[^\d]+/', $value, $matches);
        if (!empty($matches[0])) {
            $operators = [
                '>=' => Comparison::GTE,
                '>' => Comparison::GT,
                '<' => Comparison::LT,
                '<=' => Comparison::LTE,
                '<>' => Comparison::NEQ,
                '=' => Comparison::EQ,
                'gte' => Comparison::GTE,
                'gt' => Comparison::GT,
                'lt' => Comparison::LT,
                'lte' => Comparison::LTE,
                'neq' => Comparison::NEQ,
                'eq' => Comparison::EQ,
            ];
            $operator = isset($operators[$matches[0]])
                ? $operators[$matches[0]]
                : Comparison::EQ;
            $value = (int) substr($value, strlen($matches[0]));
        } else {
            $operator = Comparison::EQ;
        }
        $qb->andWhere(new Comparison(
            $this->getEntityClass() . '.' . $column,
            $operator,
            $this->createNamedParameter($qb, $value)
        ));
    }
}
