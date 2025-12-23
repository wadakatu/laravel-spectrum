<?php

namespace LaravelSpectrum\Generators;

/**
 * Generates OpenAPI tag groups and tag definitions.
 *
 * This generator creates:
 * - x-tagGroups extension for grouping tags in documentation viewers (e.g., Redoc)
 * - tags section with descriptions for the OpenAPI specification
 */
class TagGroupGenerator
{
    /**
     * Default name for the group containing ungrouped tags.
     */
    private const DEFAULT_UNGROUPED_GROUP_NAME = 'Other';

    /**
     * Generate x-tagGroups structure for OpenAPI specification.
     *
     * @param  array<string>  $usedTags  List of tags actually used in the specification
     * @return array<array{name: string, tags: array<string>}>
     */
    public function generateTagGroups(array $usedTags): array
    {
        // Filter and validate used tags
        $usedTags = $this->filterValidTags($usedTags);

        $tagGroups = config('spectrum.tag_groups', []);

        // Validate tag_groups config structure
        if (! is_array($tagGroups)) {
            return [];
        }

        if (empty($tagGroups) && empty($usedTags)) {
            return [];
        }

        $result = [];
        $groupedTags = [];

        // Build groups with only used tags
        foreach ($tagGroups as $groupName => $tags) {
            // Skip invalid group entries
            if (! is_string($groupName) || ! is_array($tags)) {
                continue;
            }

            // Filter to only string tags
            $tags = $this->filterValidTags($tags);
            $filteredTags = array_values(array_intersect($tags, $usedTags));

            if (! empty($filteredTags)) {
                $result[] = [
                    'name' => $groupName,
                    'tags' => $filteredTags,
                ];
                $groupedTags = array_merge($groupedTags, $filteredTags);
            }
        }

        // Handle ungrouped tags
        $ungroupedGroupName = config('spectrum.ungrouped_tags_group', self::DEFAULT_UNGROUPED_GROUP_NAME);
        if ($ungroupedGroupName !== null && is_string($ungroupedGroupName) && $ungroupedGroupName !== '') {
            $ungroupedTags = array_values(array_diff($usedTags, $groupedTags));

            if (! empty($ungroupedTags)) {
                $result[] = [
                    'name' => $ungroupedGroupName,
                    'tags' => $ungroupedTags,
                ];
            }
        }

        return $result;
    }

    /**
     * Generate OpenAPI tags section with descriptions.
     *
     * @param  array<string>  $usedTags  List of tags actually used in the specification
     * @return array<array{name: string, description?: string}>
     */
    public function generateTagDefinitions(array $usedTags): array
    {
        // Filter and validate used tags
        $usedTags = $this->filterValidTags($usedTags);

        if (empty($usedTags)) {
            return [];
        }

        $descriptions = config('spectrum.tag_descriptions', []);

        // Validate descriptions config structure
        if (! is_array($descriptions)) {
            $descriptions = [];
        }

        $result = [];
        foreach ($usedTags as $tag) {
            $tagDefinition = ['name' => $tag];

            if (isset($descriptions[$tag]) && is_string($descriptions[$tag]) && $descriptions[$tag] !== '') {
                $tagDefinition['description'] = $descriptions[$tag];
            }

            $result[] = $tagDefinition;
        }

        return $result;
    }

    /**
     * Check if tag groups are configured.
     */
    public function hasTagGroups(): bool
    {
        $tagGroups = config('spectrum.tag_groups', []);

        return is_array($tagGroups) && ! empty($tagGroups);
    }

    /**
     * Filter array to contain only valid non-empty string values.
     *
     * @param  array<mixed>  $tags
     * @return array<string>
     */
    private function filterValidTags(array $tags): array
    {
        return array_values(array_filter(
            $tags,
            static fn ($tag): bool => is_string($tag) && $tag !== ''
        ));
    }
}
