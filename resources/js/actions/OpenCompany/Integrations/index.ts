import Google from './Google'
import TickTick from './TickTick'

const Integrations = {
    Google: Object.assign(Google, Google),
    TickTick: Object.assign(TickTick, TickTick),
}

export default Integrations