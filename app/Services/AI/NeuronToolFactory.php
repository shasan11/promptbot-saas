<?php

namespace App\Services\AI;

use App\Models\AI\Agent;
use App\Models\AI\Run;
use App\Models\Connections\ConnectionAction;
use App\Models\User;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;

class NeuronToolFactory
{
    public function __construct(private readonly AIToolExecutionService $execution) {}

    /** @return array<int, Tool> */
    public function forAgent(Agent $agent, Run $run, ?User $actor): array
    {
        return $agent->connectionActions()->with('connection')->wherePivot('enabled', true)
            ->where('connection_actions.enabled_for_ai', true)->where('connection_actions.status', 'active')
            ->get()->map(fn (ConnectionAction $action) => $this->make($agent, $run, $action, $actor))->all();
    }

    private function make(Agent $agent, Run $run, ConnectionAction $action, ?User $actor): Tool
    {
        $tool = Tool::make($this->safeName($action), $action->description ?: $action->name);
        $schema = (array) $action->input_schema;
        foreach ((array) ($schema['properties'] ?? []) as $name => $property) {
            $property = (array) $property;
            try { $type = PropertyType::fromSchema($property['type'] ?? 'string'); } catch (\Throwable) { $type = PropertyType::STRING; }
            $tool->addProperty(new ToolProperty((string) $name, $type, (string) ($property['description'] ?? $name), in_array($name, (array) ($schema['required'] ?? []), true), (array) ($property['enum'] ?? [])));
        }
        return $tool->setCallable(fn (...$values) => $this->execution->invoke($run, $agent, $action, $this->arguments($tool, $values), $actor));
    }

    private function safeName(ConnectionAction $action): string
    {
        return substr('connection_'.preg_replace('/[^a-zA-Z0-9_]/', '_', $action->key), 0, 64);
    }

    /** @param array<int, mixed> $values
     *  @return array<string, mixed>
     */
    private function arguments(Tool $tool, array $values): array
    {
        $names = array_map(fn (ToolProperty $property) => $property->getName(), $tool->getProperties());
        return array_combine($names, array_pad($values, count($names), null)) ?: [];
    }
}
