export function renderGraph(container, graph) {
  if (!container) return;
  if (!graph || !graph.nodes || graph.nodes.length === 0) {
    container.innerHTML = '<p class="graph-empty">No relationships yet.</p>';
    return;
  }
  const list = document.createElement('ul');
  list.className = 'graph-list';
  graph.edges.slice(0, 20).forEach((edge) => {
    const item = document.createElement('li');
    item.textContent = `${edge.source} → ${edge.target}`;
    list.appendChild(item);
  });
  container.innerHTML = '';
  container.appendChild(list);
}
