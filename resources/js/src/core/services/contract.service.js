import {ethers} from "ethers";
import usdtAbi from "../../common/usdt.json";
import providerBsc from "./provider.service";

const wallet_bsc = "0x55d398326f99059ff775485246999027b3197955"; // Mainnet USDT
// const wallet_bsc = "0x921C3e8919Eb6407FB827a9EEf1687Ad1fbBEa5E"; // Testnet BBG

export const usdtContract = (wallet_public_key) => {
    const contract = new ethers.Contract(wallet_bsc, usdtAbi, providerBsc.providerBsc())
    return contract.balanceOf(wallet_public_key);
};

export default {usdtContract};
