import { graphlib, layout } from '@dagrejs/dagre';
import type { GraphEdge, GraphNode } from '@/types/cmdb';

const SIZE = {
    application: { width: 220, height: 66 },
    server: { width: 200, height: 62 },
};

/**
 * Disposition automatique en couches, de gauche à droite :
 * consommateurs -> dépendances -> serveurs -> hyperviseurs.
 */
export function layoutGraph(
    nodes: GraphNode[],
    edges: GraphEdge[],
): Record<string, { x: number; y: number }> {
    const g = new graphlib.Graph();
    g.setGraph({
        rankdir: 'LR',
        nodesep: 24,
        ranksep: 110,
        marginx: 20,
        marginy: 20,
    });
    g.setDefaultEdgeLabel(() => ({}));

    for (const n of nodes) {
        // Copie : dagre écrit x/y dans l'objet fourni
        g.setNode(n.id, { ...SIZE[n.type] });
    }
    for (const e of edges) {
        // Les liens d'hébergement pèsent moins : ils ne doivent pas tirer les VMs loin de leurs applications
        g.setEdge(e.source, e.target, { weight: e.kind === 'flow' ? 3 : 1 });
    }
    layout(g);

    const positions: Record<string, { x: number; y: number }> = {};
    for (const n of nodes) {
        const p = g.node(n.id);
        const s = SIZE[n.type];
        positions[n.id] = { x: p.x - s.width / 2, y: p.y - s.height / 2 };
    }

    return positions;
}
