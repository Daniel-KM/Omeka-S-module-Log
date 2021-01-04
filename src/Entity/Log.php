<?php declare(strict_types=1);
namespace Log\Entity;

use DateTime;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Omeka\Entity\AbstractEntity;
use Omeka\Entity\Job;
use Omeka\Entity\User;

/**
 * @Entity
 * @Table(
 *     indexes={
 *         @Index(name="owner_idx", columns={"owner_id"}),
 *         @Index(name="job_idx", columns={"job_id"}),
 *         @Index(name="reference_idx", columns={"reference"}),
 *         @Index(name="severity_idx", columns={"severity"})
 *     }
 * )
 * @HasLifecycleCallbacks
 */
class Log extends AbstractEntity
{
    /**
     * @var int
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     */
    protected $id;

    /**
     * @ManyToOne(
     *     targetEntity="Omeka\Entity\User"
     * )
     * @JoinColumn(
     *     nullable=true,
     *     onDelete="SET NULL"
     * )
     */
    protected $owner;

    /**
     * @ManyToOne(
     *     targetEntity="Omeka\Entity\Job"
     * )
     * @JoinColumn(
     *     nullable=true,
     *     onDelete="CASCADE"
     * )
     */
    protected $job;

    /**
     * @var string
     * @Column(
     *     length=190,
     *     options={"default"=""}
     * )
     */
    protected $reference = '';

    /**
     * @var int
     * @Column(
     *     type="integer",
     *     options={"default"=0}
     * )
     */
    protected $severity = 0;

    /**
     * @var string
     * @Column(type="text")
     */
    protected $message;

    /**
     * @var array
     * @Column(type="json")
     */
    protected $context;

    /**
     * @var DateTime
     * @Column(type="datetime")
     */
    protected $created;

    public function getId(): int
    {
        return $this->id;
    }

    public function setOwner(User $owner = null): AbstractEntity
    {
        $this->owner = $owner;
        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setJob(Job $job = null): AbstractEntity
    {
        $this->job = $job;
        return $this;
    }

    public function getJob(): ?Job
    {
        return $this->job;
    }

    public function setReference($reference): AbstractEntity
    {
        $this->reference = $reference;
        return $this;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function setSeverity($severity): AbstractEntity
    {
        $this->severity = (int) $severity;
        return $this;
    }

    public function getSeverity(): int
    {
        return $this->severity;
    }

    public function setMessage($message): AbstractEntity
    {
        $this->message = $message;
        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setContext(array $context): AbstractEntity
    {
        $this->context = $context;
        return $this;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function setCreated(DateTime $created): AbstractEntity
    {
        $this->created = $created;
        return $this;
    }

    public function getCreated(): DateTime
    {
        return $this->created;
    }

    /**
     * @PrePersist
     * @param LifecycleEventArgs $eventContext
     */
    public function prePersist(LifecycleEventArgs $eventContext): AbstractEntity
    {
        $this->created = new DateTime('now');
        return $this;
    }
}
