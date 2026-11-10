<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\ReferenceItem;
use PDO;

/**
 * Compétences des agents.
 *
 * Un agent tient ses compétences de trois sources : son archétype, ses formations
 * réussies, et son expérience (table Abilities_Agents). La liste complète se lit
 * TOUJOURS via la vue agent_abilities, qui indique la provenance.
 *
 * Ce repository gère des associations (agent ↔ compétence) et non des entités :
 * il n'implémente donc pas RepositoryInterface.
 */
final class AgentAbilityRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Compétences d'un agent avec leur(s) provenance(s) (requête 4).
     * Une compétence peut avoir plusieurs sources : elles sont regroupées.
     *
     * @return list<array{id: int, ability: string, sources: list<string>}>
     */
    public function findForAgent(int $agentId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT Id_Ability, ability, source FROM agent_abilities WHERE Id_Agent = :agent ORDER BY ability, source'
        );
        $statement->execute(['agent' => $agentId]);

        $abilities = [];
        foreach ($statement->fetchAll() as $row) {
            $id = (int) $row['Id_Ability'];
            $abilities[$id] ??= ['id' => $id, 'ability' => (string) $row['ability'], 'sources' => []];
            $abilities[$id]['sources'][] = (string) $row['source'];
        }

        return array_values($abilities);
    }

    /**
     * Compétences que l'agent ne possède par AUCUNE source : les seules qu'on peut
     * lui attribuer au titre de l'expérience.
     *
     * @return list<ReferenceItem>
     */
    public function findAssignableExperience(int $agentId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT ab.Id_Ability, ab.ability
             FROM Abilities ab
             WHERE NOT EXISTS (
                 SELECT 1 FROM agent_abilities aa WHERE aa.Id_Agent = :agent AND aa.Id_Ability = ab.Id_Ability
             )
             ORDER BY ab.ability'
        );
        $statement->execute(['agent' => $agentId]);

        return array_map(
            static fn (array $row): ReferenceItem => new ReferenceItem((int) $row['Id_Ability'], (string) $row['ability']),
            $statement->fetchAll(),
        );
    }

    public function addExperience(int $agentId, int $abilityId): void
    {
        $statement = $this->pdo->prepare('INSERT INTO Abilities_Agents (Id_Agent, Id_Ability) VALUES (:agent, :ability)');
        $statement->execute(['agent' => $agentId, 'ability' => $abilityId]);
    }

    /** @return bool faux si l'agent n'avait pas cette compétence d'expérience */
    public function removeExperience(int $agentId, int $abilityId): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM Abilities_Agents WHERE Id_Agent = :agent AND Id_Ability = :ability');
        $statement->execute(['agent' => $agentId, 'ability' => $abilityId]);

        return $statement->rowCount() > 0;
    }
}
