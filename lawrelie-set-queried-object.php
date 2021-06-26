<?php
namespace Lawrelie\WordPress\SetQueriedObject;
// Plugin Name: lawrelie-set-queried-object
// Description: クエリされているオブジェクトを見直すプラグイン
// Version: 0.1.0-alpha
// Requires at least: 4.4
// Tested up to: 5.7
// Requires PHP: 7.4
// Text Domain: lawrelie-set-queried-object
use WP_Query, WP_Term;
$constantName = fn(string $name): string => __NAMESPACE__ . '\\' . $name;
$define = fn(string $name, ...$args): bool => \define($constantName($name), ...$args);
function filter_parseQuery(WP_Query $query): void {
    getQueriedObject($query);
}
function getQueriedObject(WP_Query $query = null) {
    $object = !$query ? \get_queried_object() : $query->get_queried_object();
    if (!!$object) {
        return $object;
    }
    $queryIs = !$query ? function(string $name): bool {
        $isName = "\is_$name";
        return $isName();
    } : function(string $name) use($query): bool {
        $isName = "is_$name";
        return $query->$isName;
    };
    $queryGet = !$query ? '\get_query_var' : [$query, 'get'];
    if ($queryIs('author')) {
        $object = \get_userdata($queryGet('author', 0));
        $object = !$object ? \get_user_by('slug', $queryGet('author_name')) : $object;
    } elseif ($queryIs('category') || $queryIs('tag') || $queryIs('tax')) {
        if ($queryIs('category')) {
            $cat = $queryGet('cat');
            if ($cat) {
                $object = \get_term($cat, 'category');
            } else {
                $categoryName = $queryGet('category_name');
                $object = $categoryName ? \get_term_by('slug', $categoryName, 'category') : $object;
            }
        } elseif ($queryIs('tag')) {
            $tagId = $queryGet('tag_id');
            if ($tagId) {
                $object = \get_term($tagId, 'post_tag');
            } else {
                $tag = $queryGet('tag');
                $object = $tag ? \get_term_by('slug', $tag, 'post_tag') : $object;
            }
        }
        $object = $object instanceof WP_Term ? $object : null;
        if (!$object) {
            if (!empty(!$query ? \get_query_var('tax_query') : $query->tax_query->queried_terms)) {
                try {
                    $taxQueries = !$query ? new \WP_Tax_Query(\get_query_var('tax_query')) : $query->tax_query;
                    $queriedTaxonomies = \array_keys($taxQueries->queried_terms);
                    $matchedTaxonomy = \reset($queriedTaxonomies);
                    $taxQuery = $taxQueries->queried_terms[$matchedTaxonomy];
                    if (!empty($taxQuery['terms'])) {
                        $term = \reset($taxQuery['terms']);
                        $object = 'term_id' === $taxQuery['field'] ? \get_term($term, $matchedTaxonomy) : \get_term_by($taxQuery['field'], $term, $matchedTaxonomy);
                    }
                } catch (\Throwable $e) {}
            }
            $object = $object instanceof WP_Term ? $object : null;
        }
    }
    if (!$object) {
        return null;
    } elseif (!$query) {
        return $object;
    }
    $query->queried_object = $object;
    return $query->queried_object;
}
$filters = [
    'parse_query' => ['filter_parseQuery' => [9]],
];
foreach ($filters as $tag => $functionsToAdd) {
    foreach ($functionsToAdd as $functionToAdd => $args) {
        \add_filter($tag, $constantName($functionToAdd), ...$args);
    }
}
