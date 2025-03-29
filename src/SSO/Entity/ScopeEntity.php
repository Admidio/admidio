<?php

namespace Admidio\SSO\Entity;

use League\OAuth2\Server\Entities\ScopeEntityInterface;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Entity\Entity;

class ScopeEntity extends Entity implements ScopeEntityInterface
{
    public function __construct(Database $database, int|string $id = '') {
        parent::__construct($database, TBL_OIDC_SCOPES, 'osc');
        if (is_numeric($id)) {
            $this->readDataById($id);
        } else {
            $this->readDataByColumns([$this->columnPrefix . '_scope' => $id]);
        }
    }

    /**
     * Get a description of the scope.
     */
    public function getDescription() {
        return $this->getValue($this->columnPrefix . '_description');
    }
    public function setDescription($description) {
        return $this->setValue($this->columnPrefix . '_description', $description);
    }

    public function getIdentifier(): string {
        return $this->getValue($this->columnPrefix . '_scope');
    }
    public function setIdentifier($scope) {
        return $this->setValue($this->columnPrefix . '_scope', $scope);
    }

    /**
     * Convert scope to string format.
     */
    public function jsonSerialize()
    {
        return $this->getIdentifier();
    }
}
