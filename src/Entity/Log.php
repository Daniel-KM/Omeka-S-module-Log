<?php declare(strict_types=1);

namespace Log\Entity;

use DateTime;
use Omeka\Entity\AbstractEntity;
use Omeka\Entity\Job;
use Omeka\Entity\User;

/**
 * @Entity
 * @Table(
 *     indexes={
 *         @Index(
 *             columns={"reference"}
 *         ),
 *         @Index(
 *             columns={"severity"}
 *         )
 *     }
 * )
 */
class Log extends AbstractEntity
{
    /**
     * @var int
     *
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     */
    protected $id;

    /**
     * @var \Omeka\Entity\User
     *
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
     * @var \Omeka\Entity\Job
     *
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
     *
     * @Column(
     *     length=190,
     *     options={
     *         "default":""
     *     }
     * )
     */
    protected $reference = '';

    /**
     * @var int
     *
     * @Column(
     *     type="integer",
     *     options={
     *         "default":0
     *     }
     * )
     */
    protected $severity = 0;

    /**
     * @var string
     *
     * @Column(
     *     type="text"
     * )
     */
    protected $message;

    /**
     * @var array
     *
     * @Column(
     *     type="json"
     * )
     */
    protected $context;

    /**
     * @var DateTime
     *
     * @Column(
     *     type="datetime",
     *     nullable=false,
     *     options={
     *         "default": "CURRENT_TIMESTAMP"
     *     }
     * )
     */
    protected $created;

    public function getId()
    {
        return $this->id;
    }

    public function setOwner(User $owner = null): self
    {
        $this->owner = $owner;
        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setJob(Job $job = null): self
    {
        $this->job = $job;
        return $this;
    }

    public function getJob(): ?Job
    {
        return $this->job;
    }

    public function setReference($reference): self
    {
        $this->reference = $reference;
        return $this;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function setSeverity($severity): self
    {
        $this->severity = (int) $severity;
        return $this;
    }

    public function getSeverity(): int
    {
        return $this->severity;
    }

    public function setMessage($message): self
    {
        $this->message = $message;
        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setContext(array $context): self
    {
        $this->context = $context;
        return $this;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function setCreated(DateTime $created): self
    {
        $this->created = $created;
        return $this;
    }

    public function getCreated(): DateTime
    {
        return $this->created;
    }
}
