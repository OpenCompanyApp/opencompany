import { ref, watch, onUnmounted, type Ref } from 'vue'
import { Application, Container, Graphics, Text, TextStyle, Circle, Sprite, Assets } from 'pixi.js'
import { forceSimulation, forceLink, forceManyBody, forceCenter, forceCollide } from 'd3-force'
import type { SimulationNodeDatum, SimulationLinkDatum } from 'd3-force'
import {
  NODE_COLORS, NODE_RADIUS, STATUS_OPACITY, STATUS_PULSE, GLOW,
  EDGE, PARTICLE, STARFIELD, LABEL, FORCE, getTheme,
  shortenBrain, AGENT_TYPE_ICONS,
  type ConstellationTheme,
} from '@/Components/org/constellation.config'
import type { AgentStatus } from '@/types'

// ---- Types ----

export interface ConstellationNode {
  id: string
  name: string
  avatar: string | null
  type: 'human' | 'agent'
  agentType: string | null
  brain?: string | null
  status: string | null
  currentTask: string | null
  email: string | null
  isEphemeral: boolean | null
  managerId: string | null
  children: ConstellationNode[]
}

interface SimNode extends SimulationNodeDatum {
  id: string
  orgNode: ConstellationNode
  radius: number
  color: number
}

interface SimLink extends SimulationLinkDatum<SimNode> {
  source: SimNode
  target: SimNode
}

interface NodeVisual {
  container: Container
  glowGraphics: Graphics
  circleGraphics: Graphics
  avatarSprite: Sprite | null
  avatarMask: Graphics | null
  iconText: Text
  nameText: Text
  roleText: Text | null
  modelText: Text | null
  modelBg: Graphics | null
}

interface EdgeParticle {
  graphics: Graphics
  progress: number
  speed: number
}

// ---- Composable ----

export function useConstellation(
  canvasContainer: Ref<HTMLElement | null>,
  nodes: Ref<ConstellationNode[]>,
  isDark: Ref<boolean>,
  onNodeClick: (id: string) => void,
  onNodeHover: (node: ConstellationNode | null, x: number, y: number) => void,
) {
  let app: Application | null = null
  let simulation: ReturnType<typeof forceSimulation<SimNode>> | null = null
  let animationFrame: number | null = null
  let resizeObserver: ResizeObserver | null = null
  let wheelHandler: ((e: WheelEvent) => void) | null = null
  let theme: ConstellationTheme = getTheme(isDark.value)

  let starfieldLayer: Container
  let edgeLayer: Container
  let particleLayer: Container
  let nodeLayer: Container
  let worldContainer: Container

  let simNodes: SimNode[] = []
  let simLinks: SimLink[] = []
  let nodeVisuals: Map<string, NodeVisual> = new Map()
  let edgeGraphics: Graphics
  let particles: Map<string, EdgeParticle[]> = new Map()

  let isDragging = false
  let dragStart = { x: 0, y: 0 }
  let zoomLevel = 1
  let hoveredNodeId: string | null = null
  let prevHoveredNodeId: string | null = null
  let edgesNeedRedraw = true

  const isReady = ref(false)

  // ---- Init ----

  async function init() {
    if (!canvasContainer.value) return

    const el = canvasContainer.value
    const width = el.clientWidth
    const height = el.clientHeight
    theme = getTheme(isDark.value)

    app = new Application()
    await app.init({
      width,
      height,
      backgroundColor: theme.bg,
      antialias: true,
      resolution: window.devicePixelRatio || 1,
      autoDensity: true,
    })

    el.appendChild(app.canvas as HTMLCanvasElement)

    worldContainer = new Container()
    app.stage.addChild(worldContainer)

    starfieldLayer = new Container()
    edgeLayer = new Container()
    particleLayer = new Container()
    nodeLayer = new Container()

    worldContainer.addChild(starfieldLayer)
    worldContainer.addChild(edgeLayer)
    worldContainer.addChild(particleLayer)
    worldContainer.addChild(nodeLayer)

    buildSimulationData()
    drawStarfield(width, height)

    edgeGraphics = new Graphics()
    edgeLayer.addChild(edgeGraphics)

    await createNodeVisuals()
    // Initial draw of all node graphics
    for (const simNode of simNodes) {
      const visual = nodeVisuals.get(simNode.id)
      if (!visual) continue
      const status = (simNode.orgNode.status || 'offline') as AgentStatus
      const opacity = STATUS_OPACITY[status] ?? 0.5
      drawNodeGraphics(simNode, visual, opacity, 1.0, false, false)
    }
    createParticles()
    setupSimulation(width, height)
    setupInteractions(el)
    setupResize(el)
    startRenderLoop()

    isReady.value = true
  }

  // ---- Watch theme changes ----

  watch(isDark, (dark) => {
    theme = getTheme(dark)
    if (!app) return

    // Update canvas background
    app.renderer.background.color = theme.bg

    // Redraw starfield with new colors
    starfieldLayer.removeChildren()
    const el = canvasContainer.value
    if (el) {
      drawStarfield(el.clientWidth, el.clientHeight)
    }

    // Update text colors and redraw node graphics
    edgesNeedRedraw = true
    for (const simNode of simNodes) {
      const visual = nodeVisuals.get(simNode.id)
      if (!visual) continue
      visual.nameText.style.fill = theme.nameColor
      if (visual.roleText) {
        visual.roleText.style.fill = theme.roleColor
      }
      if (visual.modelText) {
        visual.modelText.style.fill = theme.modelTextColor
      }
      const status = (simNode.orgNode.status || 'offline') as AgentStatus
      const opacity = STATUS_OPACITY[status] ?? 0.5
      drawNodeGraphics(simNode, visual, opacity, 1.0, false, false)
    }
  })

  // ---- Flatten tree into simulation arrays ----

  function buildSimulationData() {
    simNodes = []
    simLinks = []

    const flatten = (list: ConstellationNode[]) => {
      for (const node of list) {
        const radius = node.type === 'human'
          ? NODE_RADIUS.human
          : node.agentType === 'manager'
            ? NODE_RADIUS.manager
            : NODE_RADIUS.default

        const colorKey = node.type === 'human'
          ? 'human'
          : (node.agentType || 'default')

        simNodes.push({
          id: node.id,
          orgNode: node,
          radius,
          color: NODE_COLORS[colorKey] ?? NODE_COLORS.default,
        })

        flatten(node.children)
      }
    }

    flatten(nodes.value)

    const nodeMap = new Map(simNodes.map(n => [n.id, n]))
    for (const node of simNodes) {
      if (node.orgNode.managerId) {
        const parent = nodeMap.get(node.orgNode.managerId)
        if (parent) {
          simLinks.push({ source: parent, target: node })
        }
      }
    }
  }

  // ---- Starfield ----

  function drawStarfield(width: number, height: number) {
    const g = new Graphics()
    const margin = 500
    for (let i = 0; i < STARFIELD.count; i++) {
      const x = Math.random() * (width + margin * 2) - margin
      const y = Math.random() * (height + margin * 2) - margin
      const r = STARFIELD.minRadius + Math.random() * (STARFIELD.maxRadius - STARFIELD.minRadius)
      const alpha = theme.starMinAlpha + Math.random() * (theme.starMaxAlpha - theme.starMinAlpha)

      g.circle(x, y, r)
      g.fill({ color: theme.starColor, alpha })
    }
    starfieldLayer.addChild(g)
  }

  // ---- Node visuals ----

  async function createNodeVisuals() {
    const textRes = Math.min((window.devicePixelRatio || 1) * 2, 4)

    for (const simNode of simNodes) {
      const container = new Container()
      container.eventMode = 'static'
      container.cursor = 'pointer'
      container.hitArea = new Circle(0, 0, simNode.radius * 1.2)

      const glowGraphics = new Graphics()
      container.addChild(glowGraphics)

      const circleGraphics = new Graphics()
      container.addChild(circleGraphics)

      // Try to load avatar
      let avatarSprite: Sprite | null = null
      let avatarMask: Graphics | null = null
      const avatarUrl = simNode.orgNode.avatar
      if (avatarUrl) {
        try {
          const texture = await Assets.load(avatarUrl)
          avatarSprite = new Sprite(texture)
          const size = simNode.radius * 2 * 0.85
          avatarSprite.width = size
          avatarSprite.height = size
          avatarSprite.anchor.set(0.5, 0.5)

          avatarMask = new Graphics()
          avatarMask.circle(0, 0, simNode.radius * 0.85)
          avatarMask.fill({ color: 0xffffff })
          container.addChild(avatarMask)
          container.addChild(avatarSprite)
          avatarSprite.mask = avatarMask
        } catch {
          avatarSprite = null
          avatarMask = null
        }
      }

      // Icon fallback (hidden if avatar loaded)
      const iconChar = simNode.orgNode.type === 'human'
        ? AGENT_TYPE_ICONS.human
        : AGENT_TYPE_ICONS[simNode.orgNode.agentType || ''] || '?'

      const iconText = new Text({
        text: iconChar,
        style: new TextStyle({
          fontSize: simNode.radius * 0.7,
          fill: '#ffffff',
          fontFamily: LABEL.fontFamily,
          align: 'center',
        }),
        resolution: textRes,
      })
      iconText.anchor.set(0.5, 0.5)
      if (avatarSprite) iconText.visible = false
      container.addChild(iconText)

      const nameText = new Text({
        text: simNode.orgNode.name,
        style: new TextStyle({
          fontSize: LABEL.nameSize,
          fill: theme.nameColor,
          fontFamily: LABEL.fontFamily,
          align: 'center',
        }),
        resolution: textRes,
      })
      nameText.anchor.set(0.5, 0)
      container.addChild(nameText)

      // Role label (e.g., "Manager", "Writer", "Owner")
      let roleText: Text | null = null
      const roleLabel = simNode.orgNode.type === 'human'
        ? 'Owner'
        : simNode.orgNode.agentType
          ? simNode.orgNode.agentType.charAt(0).toUpperCase() + simNode.orgNode.agentType.slice(1)
          : null

      if (roleLabel) {
        roleText = new Text({
          text: roleLabel,
          style: new TextStyle({
            fontSize: LABEL.modelSize,
            fill: theme.roleColor,
            fontFamily: LABEL.fontFamily,
            align: 'center',
          }),
          resolution: textRes,
        })
        roleText.anchor.set(0.5, 0)
        container.addChild(roleText)
      }

      let modelText: Text | null = null
      let modelBg: Graphics | null = null
      const brainLabel = shortenBrain(simNode.orgNode.brain)
      if (brainLabel && simNode.orgNode.type === 'agent') {
        modelBg = new Graphics()
        container.addChild(modelBg)

        modelText = new Text({
          text: brainLabel,
          style: new TextStyle({
            fontSize: LABEL.modelSize,
            fill: theme.modelTextColor,
            fontFamily: LABEL.fontFamily,
            align: 'center',
          }),
          resolution: textRes,
        })
        modelText.anchor.set(0.5, 0)
        container.addChild(modelText)
      }

      nodeLayer.addChild(container)

      nodeVisuals.set(simNode.id, {
        container,
        glowGraphics,
        circleGraphics,
        avatarSprite,
        avatarMask,
        iconText,
        nameText,
        roleText,
        modelText,
        modelBg,
      })
    }
  }

  // ---- Particles ----

  function createParticles() {
    for (const link of simLinks) {
      const linkKey = `${link.source.id}-${link.target.id}`
      const linkParticles: EdgeParticle[] = []

      for (let i = 0; i < PARTICLE.count; i++) {
        const g = new Graphics()
        g.circle(0, 0, PARTICLE.radius)
        g.fill({ color: link.source.color, alpha: PARTICLE.alpha })
        particleLayer.addChild(g)

        linkParticles.push({
          graphics: g,
          progress: i / PARTICLE.count,
          speed: PARTICLE.speed * (0.8 + Math.random() * 0.4),
        })
      }

      particles.set(linkKey, linkParticles)
    }
  }

  // ---- d3-force ----

  function setupSimulation(width: number, height: number) {
    simulation = forceSimulation<SimNode>(simNodes)
      .force('charge', forceManyBody<SimNode>().strength(FORCE.chargeStrength))
      .force('link', forceLink<SimNode, SimLink>(simLinks)
        .id(d => d.id)
        .distance(FORCE.linkDistance)
        .strength(FORCE.linkStrength)
      )
      .force('center', forceCenter(width / 2, height / 2).strength(FORCE.centerStrength))
      .force('collision', forceCollide<SimNode>().radius(d => d.radius + 10))
      .alphaDecay(FORCE.alphaDecay)
      .velocityDecay(FORCE.velocityDecay)
  }

  // ---- Render loop ----

  function startRenderLoop() {
    let lastTime = performance.now()

    const render = (now: number) => {
      const dt = Math.min((now - lastTime) / 1000, 0.1)
      lastTime = now

      updatePositions()
      updateParticles(dt)

      animationFrame = requestAnimationFrame(render)
    }

    animationFrame = requestAnimationFrame(render)
  }

  function drawNodeGraphics(simNode: SimNode, visual: NodeVisual, opacity: number, pulseScale: number, isHovered: boolean, shouldPulse: boolean) {
    // Glow rings
    visual.glowGraphics.clear()
    const glowBoost = isHovered ? 1.8 : (shouldPulse ? GLOW.workingBoost : 1)
    const glowAlpha = GLOW.maxAlpha * opacity * glowBoost
    for (let i = GLOW.layers; i > 0; i--) {
      const ratio = i / GLOW.layers
      const r = simNode.radius * (1 + (GLOW.radiusMultiplier - 1) * ratio) * pulseScale
      visual.glowGraphics.circle(0, 0, r)
      visual.glowGraphics.fill({ color: simNode.color, alpha: glowAlpha * (1 - ratio) * 0.5 })
    }

    // Main circle
    visual.circleGraphics.clear()
    visual.circleGraphics.circle(0, 0, simNode.radius * pulseScale)
    visual.circleGraphics.fill({ color: simNode.color, alpha: opacity })

    // Subtle ring
    visual.circleGraphics.circle(0, 0, simNode.radius * pulseScale)
    visual.circleGraphics.stroke({ color: theme.ringColor, width: 1.5, alpha: opacity * theme.ringAlpha })

    // Avatar mask
    if (visual.avatarMask) {
      visual.avatarMask.clear()
      visual.avatarMask.circle(0, 0, simNode.radius * 0.85 * pulseScale)
      visual.avatarMask.fill({ color: 0xffffff })
    }
    if (visual.avatarSprite) {
      const size = simNode.radius * 2 * 0.85 * pulseScale
      visual.avatarSprite.width = size
      visual.avatarSprite.height = size
      visual.avatarSprite.alpha = opacity
    }

    // Model badge bg
    if (visual.modelText && visual.modelBg) {
      const labelBase = simNode.radius * pulseScale + LABEL.yOffset + LABEL.nameSize + 2
      const modelOffset = visual.roleText ? labelBase + LABEL.modelSize + 2 : labelBase
      const tw = visual.modelText.width
      const th = visual.modelText.height
      visual.modelBg.clear()
      visual.modelBg.roundRect(-tw / 2 - 4, modelOffset, tw + 8, th + 4, 3)
      visual.modelBg.fill({ color: theme.modelBgColor, alpha: theme.modelBgAlpha * opacity })
    }
  }

  function updatePositions() {
    // Only redraw edges while simulation is still settling
    const simAlpha = simulation?.alpha() ?? 0
    if (edgesNeedRedraw || simAlpha > 0.001) {
      edgeGraphics.clear()
      for (const link of simLinks) {
        const src = link.source
        const tgt = link.target
        if (src.x == null || src.y == null || tgt.x == null || tgt.y == null) continue

        edgeGraphics.moveTo(src.x, src.y)
        edgeGraphics.lineTo(tgt.x, tgt.y)
        edgeGraphics.stroke({ color: theme.edgeGlowColor, width: EDGE.glowWidth, alpha: EDGE.glowAlpha })

        edgeGraphics.moveTo(src.x, src.y)
        edgeGraphics.lineTo(tgt.x, tgt.y)
        edgeGraphics.stroke({ color: theme.edgeColor, width: EDGE.width, alpha: theme.edgeAlpha })
      }
      edgesNeedRedraw = false
    }

    const time = performance.now() / 1000
    const hoverChanged = hoveredNodeId !== prevHoveredNodeId
    prevHoveredNodeId = hoveredNodeId

    for (const simNode of simNodes) {
      if (simNode.x == null || simNode.y == null) continue

      const visual = nodeVisuals.get(simNode.id)
      if (!visual) continue

      const status = (simNode.orgNode.status || 'offline') as AgentStatus
      const isHovered = hoveredNodeId === simNode.id
      const baseOpacity = STATUS_OPACITY[status] ?? 0.5
      const opacity = isHovered ? Math.min(baseOpacity + 0.3, 1.0) : baseOpacity
      const shouldPulse = STATUS_PULSE[status] ?? false

      const pulseScale = shouldPulse
        ? 1.0 + 0.08 * Math.sin(time * 2.5)
        : 1.0
      const hoverScale = isHovered ? 1.12 : 1.0

      // Position is always cheap to update
      visual.container.x = simNode.x
      visual.container.y = simNode.y
      visual.container.scale.set(hoverScale)

      // Only redraw expensive Graphics if this node is animating or hover changed
      const wasHovered = hoverChanged && (simNode.id === hoveredNodeId || simNode.id === prevHoveredNodeId)
      if (shouldPulse || hoverChanged) {
        drawNodeGraphics(simNode, visual, opacity, pulseScale, isHovered, shouldPulse)
      }

      // Alpha and label positions are cheap — always update
      visual.iconText.alpha = opacity
      visual.nameText.y = simNode.radius * pulseScale + LABEL.yOffset
      visual.nameText.alpha = opacity * 0.9

      let labelOffset = simNode.radius * pulseScale + LABEL.yOffset + LABEL.nameSize + 2
      if (visual.roleText) {
        visual.roleText.y = labelOffset
        visual.roleText.alpha = opacity * 0.85
        labelOffset += LABEL.modelSize + 2
      }

      if (visual.modelText) {
        visual.modelText.y = labelOffset + 2
        visual.modelText.alpha = opacity * 0.9
      }
    }
  }

  function updateParticles(dt: number) {
    for (const link of simLinks) {
      const src = link.source
      const tgt = link.target
      if (src.x == null || src.y == null || tgt.x == null || tgt.y == null) continue

      const linkKey = `${src.id}-${tgt.id}`
      const linkParticles = particles.get(linkKey)
      if (!linkParticles) continue

      const isActive = src.orgNode.status === 'working' || tgt.orgNode.status === 'working'

      for (const p of linkParticles) {
        if (isActive) {
          p.progress += p.speed * dt
          if (p.progress > 1) p.progress -= 1

          p.graphics.x = src.x + (tgt.x - src.x) * p.progress
          p.graphics.y = src.y + (tgt.y - src.y) * p.progress
          p.graphics.alpha = PARTICLE.alpha
          p.graphics.visible = true
        } else {
          p.graphics.visible = false
        }
      }
    }
  }

  // ---- Interactions ----

  function setupInteractions(el: HTMLElement) {
    if (!app) return

    app.stage.eventMode = 'static'
    app.stage.hitArea = app.screen

    // Zoom
    wheelHandler = (e: WheelEvent) => {
      e.preventDefault()
      const zoomDelta = e.deltaY > 0 ? 0.9 : 1.1
      const newZoom = Math.max(0.3, Math.min(3, zoomLevel * zoomDelta))

      const rect = el.getBoundingClientRect()
      const mx = e.clientX - rect.left
      const my = e.clientY - rect.top

      const beforeX = (mx - worldContainer.x) / worldContainer.scale.x
      const beforeY = (my - worldContainer.y) / worldContainer.scale.y

      zoomLevel = newZoom
      worldContainer.scale.set(zoomLevel)

      worldContainer.x = mx - beforeX * zoomLevel
      worldContainer.y = my - beforeY * zoomLevel
    }
    el.addEventListener('wheel', wheelHandler, { passive: false })

    // Pan
    app.stage.on('pointerdown', (e) => {
      isDragging = true
      dragStart = { x: e.globalX - worldContainer.x, y: e.globalY - worldContainer.y }
    })

    app.stage.on('pointermove', (e) => {
      if (!isDragging) return
      worldContainer.x = e.globalX - dragStart.x
      worldContainer.y = e.globalY - dragStart.y
    })

    app.stage.on('pointerup', () => { isDragging = false })
    app.stage.on('pointerupoutside', () => { isDragging = false })

    // Node hover & click
    for (const simNode of simNodes) {
      const visual = nodeVisuals.get(simNode.id)
      if (!visual) continue

      visual.container.on('pointerover', (e) => {
        hoveredNodeId = simNode.id
        onNodeHover(simNode.orgNode, e.globalX, e.globalY)
      })

      visual.container.on('pointerout', () => {
        hoveredNodeId = null
        onNodeHover(null, 0, 0)
      })

      visual.container.on('pointertap', (e) => {
        e.stopPropagation()
        onNodeClick(simNode.orgNode.id)
      })
    }
  }

  // ---- Resize ----

  function setupResize(el: HTMLElement) {
    resizeObserver = new ResizeObserver((entries) => {
      if (!app) return
      const { width, height } = entries[0].contentRect
      if (width > 0 && height > 0) {
        app.renderer.resize(width, height)
        app.stage.hitArea = app.screen
        edgesNeedRedraw = true
        simulation?.force('center', forceCenter(width / 2, height / 2).strength(FORCE.centerStrength))
        simulation?.alpha(0.3).restart()
      }
    })
    resizeObserver.observe(el)
  }

  // ---- Update (for real-time changes) ----

  function updateNodeData(updatedNodes: ConstellationNode[]) {
    const flatNew: ConstellationNode[] = []
    const flattenNew = (list: ConstellationNode[]) => {
      for (const n of list) { flatNew.push(n); flattenNew(n.children) }
    }
    flattenNew(updatedNodes)

    const newMap = new Map(flatNew.map(n => [n.id, n]))
    for (const sn of simNodes) {
      const updated = newMap.get(sn.id)
      if (updated) {
        sn.orgNode = updated
      }
    }
  }

  // ---- Cleanup ----

  function destroy() {
    if (animationFrame != null) cancelAnimationFrame(animationFrame)
    simulation?.stop()
    resizeObserver?.disconnect()
    if (wheelHandler && canvasContainer.value) {
      canvasContainer.value.removeEventListener('wheel', wheelHandler)
    }
    app?.destroy(true, { children: true })
    app = null
    simulation = null
    nodeVisuals.clear()
    particles.clear()
  }

  onUnmounted(destroy)

  return {
    init,
    destroy,
    updateNodeData,
    isReady,
  }
}
