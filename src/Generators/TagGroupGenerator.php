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
     * Generate x-tagGroups structure for OpenAPI specification.
     *
     * @param  array<string>  $usedTags  List of tags actually used in the specification
     * @return array<array{name: string, tags: array<string>}>
     */
    public function generateTagGroups(array $usedTags): array
    {
        /** @var array<string, array<string>> $tagGroups */
        $tagGroups = config('spectrum.tag_groups', []);

        if (empty($tagGroups) && empty($usedTags)) {
            return [];
        }

        $result = [];
        $groupedTags = [];

        // Build groups with only used tags
        foreach ($tagGroups as $groupName => $tags) {
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
        $ungroupedGroupName = config('spectrum.ungrouped_tags_group', 'Other');
        if ($ungroupedGroupName !== null) {
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
        if (empty($usedTags)) {
            return [];
        }

        /** @var array<string, string> $descriptions */
        $descriptions = config('spectrum.tag_descriptions', []);

        $result = [];
        foreach ($usedTags as $tag) {
            $tagDefinition = ['name' => $tag];

            if (isset($descriptions[$tag]) && $descriptions[$tag] !== '') {
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

        return ! empty($tagGroups);
    }
}
